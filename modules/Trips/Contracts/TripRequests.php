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
}
