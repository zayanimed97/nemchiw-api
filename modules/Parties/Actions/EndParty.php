<?php

namespace Modules\Parties\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Parties\Models\Party;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

final class EndParty
{
    /**
     * The leader ends the party for everyone, before the scan only: after it, ending would
     * withdraw everyone from a trip they joined, so people leave one by one instead.
     */
    public function handle(string $partyId, string $callerId): void
    {
        DB::transaction(function () use ($partyId, $callerId) {
            Parties::lockPerson($callerId);
            $party = Parties::lockedOrFail($partyId);
            if ($party->leader_id !== $callerId) {
                throw new ApiException(ApiErrorCode::NotLeader, 'Only the leader can end the party');
            }
            if ($party->state === Party::GOING) {
                throw new ApiException(ApiErrorCode::PartyLocked, 'The party already joined its trip');
            }
            Parties::finish($party, 'ended', $callerId, $callerId);
        }, 3);
    }
}
