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
            $current = Parties::openFor($userId);
            $party = Parties::lockedOrFail($partyId);
            $row = $this->invitation($party, $userId);

            if ($party->state !== Party::FORMING) {
                throw new ApiException(ApiErrorCode::PartyLocked, 'This party already asked for a trip');
            }
            if ($current !== null) {
                if ($current->state !== Party::FORMING) {
                    throw new ApiException(ApiErrorCode::AlreadyInParty, 'Your party is already asking for a trip');
                }
                $this->leave->handle($current, $userId);
            }

            $row->update(['state' => PartyMember::IN, 'answered_at' => now()]);
            $this->friends->add($party->leader_id, $userId, ['kind' => 'party']);
            Parties::changed($party, 'accepted', $userId, $userId);
        });
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
