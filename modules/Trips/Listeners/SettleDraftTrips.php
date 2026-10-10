<?php

namespace Modules\Trips\Listeners;

use Modules\Shared\Places\PlaceModerated;
use Modules\Shared\Places\PlaceStatus;
use Modules\Trips\Actions\CancelTrip;
use Modules\Trips\Models\Trip;
use Modules\Trips\Support\TripStatus;

/** A draft trip waits for its place: approval publishes it, rejection cancels it (no penalty). */
final class SettleDraftTrips
{
    public function __construct(private readonly CancelTrip $cancel) {}

    public function handle(PlaceModerated $event): void
    {
        Trip::query()->where('status', TripStatus::DRAFT)
            ->where('destination_kind', $event->kind)->where('destination_id', $event->id)
            ->each(function (Trip $trip) use ($event) {
                if ($event->status === PlaceStatus::LIVE && $trip->starts_at->isFuture()) {
                    $trip->forceFill(['status' => TripStatus::PUBLISHED])->save();
                } else {
                    ($this->cancel)($trip, 'system', counts: false);
                }
            });
    }
}
