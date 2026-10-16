<?php

namespace Modules\Parties\Services;

use Illuminate\Support\Facades\DB;
use Modules\Parties\Actions\Parties;
use Modules\Parties\Contracts\PartyRoster;
use Modules\Parties\Models\Party;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

final class EloquentPartyRoster implements PartyRoster
{
    public function __construct(private readonly Readiness $readiness) {}

    public function markAsking(string $partyId, string $requestId): void
    {
        DB::transaction(function () use ($partyId, $requestId) {
            $party = Parties::lockedOrFail($partyId);
            $party->forceFill(['state' => Party::ASKING, 'request_id' => $requestId])->save();
            Parties::changed($party, 'request_asked', null, $party->leader_id);
        });
    }

    /**
     * Locks the leader, then the party (the order every party action uses; Trips locks the trip
     * after), and refuses anyone who is not the leader or a party that is not forming.
     */
    public function forAsk(string $partyId, string $leaderId, string $tripId): array
    {
        return DB::transaction(function () use ($partyId, $leaderId, $tripId) {
            Parties::lockPerson($leaderId);
            $party = Parties::lockedOrFail($partyId);
            if ($party->leader_id !== $leaderId) {
                throw new ApiException(ApiErrorCode::NotLeader, 'Only the leader can do this');
            }
            if ($party->state !== Party::FORMING) {
                throw new ApiException(ApiErrorCode::PartyLocked, 'The party already asked for a trip');
            }

            return [
                'memberIds' => $party->memberIds(),
                'blockers' => $this->readiness->forTrip($party, $tripId)['blockers'],
            ];
        });
    }
}
