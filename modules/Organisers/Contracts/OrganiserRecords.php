<?php

namespace Modules\Organisers\Contracts;

/** Certification and the reputation counters other modules (Trips) read and move. */
interface OrganiserRecords
{
    public const COUNTERS = ['trips_run', 'campers_joined', 'cancelled_trips', 'cancelled_campers'];

    public function isCertified(string $userId): bool;

    /**
     * @param  list<string>  $userIds
     * @return array<string, array{certified: bool, record: array{tripsRun: int, campersJoined: int, cancelledTrips: int, cancelledCampers: int}>>
     */
    public function cards(array $userIds): array;

    public function publicPhone(string $userId): ?string;

    public function bump(string $userId, string $counter, int $by = 1): void;
}
