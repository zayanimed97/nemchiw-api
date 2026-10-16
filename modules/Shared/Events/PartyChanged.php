<?php

namespace Modules\Shared\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A party moved. `change`: invited, accepted, declined, reminded, removed, left,
 * leader_changed, ended, auto_ended, and as the party follows its trip request:
 * request_asked, request_accepted, request_scanned, request_ended. `userId` is the person it is about (null for a
 * placeholder), `byId` who did it (null for the system). `memberIds` is the leader plus
 * the `in` members after the change. Dispatched after the transaction commits.
 */
final class PartyChanged implements ShouldDispatchAfterCommit
{
    /** @param list<string> $memberIds */
    public function __construct(
        public readonly string $partyId,
        public readonly ?string $requestId,
        public readonly string $change,
        public readonly ?string $userId,
        public readonly ?string $byId,
        public readonly string $leaderId,
        public readonly array $memberIds,
    ) {}
}
