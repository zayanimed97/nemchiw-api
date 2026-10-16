<?php

namespace Modules\Parties\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

final class AnswerInvitation
{
    public function __construct(private readonly LeaveParty $leave, private readonly Friends $friends) {}

    public function accept(string $partyId, string $userId): void
    {
        DB::transaction(function () use ($partyId, $userId) {
            Parties::lockPerson($userId);
            // Both parties in id order, so two crosswise accepts queue instead of deadlocking.
            $currentId = Parties::openFor($userId, lock: false)?->id;
            $locked = Party::query()->whereIn('id', array_filter([$currentId, strtolower($partyId)]))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $current = $currentId === null ? null : $locked->get($currentId);
            if ($current !== null && ($current->state === Party::OVER || ! Parties::isIn($current, $userId))) {
                $current = null;
            }
            $party = $locked->get(strtolower($partyId));
            if ($party === null || $party->state === Party::OVER) {
                throw new ApiException(ApiErrorCode::NotFound, 'Not found');
            }
            $row = $this->invitation($party, $userId);

            if ($party->state !== Party::FORMING) {
                throw new ApiException(ApiErrorCode::PartyLocked, 'This party already asked for a trip');
            }
            if ($current !== null) {
                if ($current->state !== Party::FORMING) {
                    throw new ApiException(ApiErrorCode::AlreadyInParty, 'Your party is already asking for a trip');
                }
                ($this->leave)($current, $userId);
            }

            $row->update(['state' => PartyMember::IN, 'answered_at' => now()]);
            $this->friends->add($party->leader_id, $userId, ['kind' => 'party']);
            Parties::changed($party, 'accepted', $userId, $userId);
        }, 3);
    }

    public function decline(string $partyId, string $userId): void
    {
        DB::transaction(function () use ($partyId, $userId) {
            $party = Parties::lockedOrFail($partyId);
            $row = $this->invitation($party, $userId);
            $row->update(['state' => PartyMember::DECLINED, 'answered_at' => now()]);
            Parties::changed($party, 'declined', $userId, $userId);
        });
    }

    private function invitation(Party $party, string $userId): PartyMember
    {
        $row = PartyMember::query()->where('party_id', $party->id)->where('user_id', $userId)
            ->where('state', PartyMember::INVITED)->lockForUpdate()->first();
        if ($row === null) {
            throw new ApiException(ApiErrorCode::NotFound, 'Not found');
        }

        return $row;
    }
}
