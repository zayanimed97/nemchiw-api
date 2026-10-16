<?php

namespace Modules\Parties\Services;

use Modules\Parties\Contracts\PartyRoster;
use Modules\Parties\Models\Party;

final class EloquentPartyRoster implements PartyRoster
{
    public function __construct(private readonly Readiness $readiness) {}

    public function forAsk(string $partyId, string $leaderId, string $tripId): array
    {
        $party = Party::query()->findOrFail($partyId);

        return [
            'memberIds' => $party->memberIds(),
            'blockers' => $this->readiness->forTrip($party, $tripId)['blockers'],
        ];
    }
}
