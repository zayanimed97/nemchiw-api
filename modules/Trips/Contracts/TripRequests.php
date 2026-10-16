<?php

namespace Modules\Trips\Contracts;

/** What other modules (Chat, Push) read about requests and trips. Times are ISO strings. */
interface TripRequests
{
    /**
     * Unknown ids are left out.
     *
     * @param  list<string>  $requestIds
     * @return array<string, array{requestId: string, tripId: string, tripTitle: string, tripKind: string, startsAt: string, endsAt: string, meetupAt: string, tripStatus: string, requestStatus: string, leaderId: string, organiserId: string}>
     */
    public function context(array $requestIds): array;

    /** @return array{tripId: string, title: string, kind: string, organiserId: string, startsAt: string, endsAt: string, meetupAt: string, status: string}|null */
    public function trip(string $tripId): ?array;

    /**
     * @param  list<string>  $statuses
     * @return list<array{requestId: string, leaderId: string, status: string}>
     */
    public function requestsOfTrip(string $tripId, array $statuses): array;

    /** The open request (pending, accepted, joined) this person is a member of for the trip. */
    public function openRequestFor(string $tripId, string $userId): ?string;

    /**
     * A party member leaves the request, or is removed by `$by` (a user id; the same id means they left).
     * Deletes the member row. Nobody left: the request is withdrawn. Raises `member_left`
     * or `member_removed` with the person's id as the note. Does nothing to a finished request.
     */
    public function removePartyMember(string $requestId, string $userId, string $by): void;

    /** The party's lead moved to another member. Raises `leader_changed`. */
    public function changeLeader(string $requestId, string $leaderId): void;

    /** The party ended: an open request is withdrawn. Does nothing to a finished one. */
    public function withdrawForParty(string $requestId): void;
}
