<?php

namespace Modules\Parties\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

final class EndParty
{
    /** The leader ends the party for everyone. */
    public function handle(string $partyId, string $callerId): void
    {
        DB::transaction(function () use ($partyId, $callerId) {
            Parties::lockPerson($callerId);
            $party = Parties::lockedOrFail($partyId);
            if ($party->leader_id !== $callerId) {
                throw new ApiException(ApiErrorCode::NotLeader, 'Only the leader can end the party');
            }
            Parties::finish($party, 'ended', $callerId, $callerId);
        }, 3);
    }
}
