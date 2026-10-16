<?php

namespace Modules\Parties\Contracts;

/** What Trips asks Parties when a party asks to join a trip. */
interface PartyRoster
{
    /**
     * Who goes with the leader (the leader plus the `in` members) and who stops the party from asking.
     * Trips refuses with `party_not_ready` when `blockers` is not empty.
     *
     * @return array{memberIds: list<string>, blockers: list<array{memberId: ?string, userId: ?string, name: ?string, reason: string, missing: list<string>}>}
     */
    public function forAsk(string $partyId, string $leaderId, string $tripId): array;
}
