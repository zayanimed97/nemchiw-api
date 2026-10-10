<?php

namespace Modules\Trips\Services;

use Modules\Identity\Contracts\CampCounts;
use Modules\Trips\Models\TripRequestMember;
use Modules\Trips\Support\RequestStatus;
use Modules\Trips\Support\TripStatus;

final class TripCampCounts implements CampCounts
{
    public function for(string $userId): int
    {
        return TripRequestMember::query()
            ->join('trip_requests', 'trip_requests.id', '=', 'trip_request_members.request_id')
            ->join('trips', 'trips.id', '=', 'trip_requests.trip_id')
            ->where('trip_request_members.user_id', $userId)
            ->whereNotNull('trip_request_members.scanned_at')
            ->where('trip_requests.status', RequestStatus::JOINED)
            ->where('trips.status', TripStatus::FINISHED)
            ->count();
    }
}
