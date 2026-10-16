<?php

namespace Modules\Parties\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

final class RemindMember
{
    /** The leader nudges someone who has not answered: once per member per 12 hours. The push itself is sent from the PartyChanged event. */
    public function handle(string $partyId, string $callerId, string $memberId): Party
    {
        return DB::transaction(function () use ($partyId, $callerId, $memberId) {
            Parties::lockPerson($callerId);
            $party = Parties::lockedOrFail($partyId);
            $row = Parties::leaderRow($party, $callerId, $memberId, [PartyMember::INVITED]);
            if ($row->user_id === null) {
                throw new ApiException(ApiErrorCode::NotFound, 'Not found'); // a placeholder has no phone to push to
            }

            $next = $row->reminded_at?->copy()->addHours((int) config('parties.remind_hours'));
            if ($next !== null && $next->isFuture()) {
                throw new ApiException(ApiErrorCode::RateLimited, 'Already reminded', max(1, $next->getTimestamp() - now()->getTimestamp()));
            }
            $row->update(['reminded_at' => now()]);
            Parties::changed($party, 'reminded', (string) $row->user_id, $callerId);

            return $party;
        }, 3);
    }
}
