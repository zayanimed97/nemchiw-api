<?php

namespace Modules\Trips\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Support\RequestStatus;
use Modules\Trips\Support\TripStatus;

final class CancelTrip
{
    public function __construct(private readonly RequestTransitions $transitions, private readonly OrganiserRecords $records) {}

    /** By the organiser: counts. By the system (rejected draft place): pass $counts = false. */
    public function __invoke(Trip $trip, string $actorId, bool $counts = true): Trip
    {
        return DB::transaction(function () use ($trip, $actorId, $counts) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            if ($counts && $trip->organiser_id !== $actorId) {
                throw new ApiException(ApiErrorCode::Forbidden, 'Only the organiser can cancel this trip');
            }
            if (! in_array($trip->status, [TripStatus::DRAFT, TripStatus::PUBLISHED], true)) {
                throw new ApiException(ApiErrorCode::TripNotOpen, 'This trip is already closed');
            }
            $cancelledCampers = 0;
            $by = $counts ? 'organiser' : 'system';
            foreach (TripRequest::query()->where('trip_id', $trip->id)->whereIn('status', RequestStatus::OPEN)->get() as $request) {
                if ($request->status === RequestStatus::PENDING) {
                    $this->transitions->end($request, RequestStatus::DECLINED, $by, 'trip_cancelled');
                } else {
                    $this->transitions->end($request, RequestStatus::CANCELLED, $by, 'trip_cancelled');
                    $cancelledCampers += max(1, $request->members()->count());
                }
            }
            $wasPublished = $trip->status === TripStatus::PUBLISHED;
            $trip->forceFill(['status' => TripStatus::CANCELLED, 'cancelled_at' => now()])->save();
            if ($counts && $wasPublished) {
                $this->records->bump($trip->organiser_id, 'cancelled_trips');
                if ($cancelledCampers > 0) {
                    $this->records->bump($trip->organiser_id, 'cancelled_campers', $cancelledCampers);
                }
            }

            return $trip;
        });
    }
}
