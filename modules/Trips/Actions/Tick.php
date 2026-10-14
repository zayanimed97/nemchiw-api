<?php

namespace Modules\Trips\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Shared\Events\TripFinished;
use Modules\Shared\Events\TripReminderDue;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Support\RequestStatus;
use Modules\Trips\Support\TripStatus;

/** Hourly: requests left open at the start lapse, ended trips finish and credit their organiser. */
final class Tick
{
    public function __construct(
        private readonly RequestTransitions $transitions,
        private readonly OrganiserRecords $records,
        private readonly CancelTrip $cancel,
    ) {}

    public function __invoke(): void
    {
        $started = Trip::query()->where('starts_at', '<=', now());

        TripRequest::query()
            ->whereIn('status', [RequestStatus::PENDING, RequestStatus::ACCEPTED])
            ->whereIn('trip_id', (clone $started)->where('status', TripStatus::PUBLISHED)->select('id'))
            ->each(fn (TripRequest $request) => $this->transitions->end($request, RequestStatus::LAPSED, 'system'));

        (clone $started)->where('status', TripStatus::DRAFT)->each(fn (Trip $trip) => ($this->cancel)($trip, 'system', counts: false));

        Trip::query()->where('status', TripStatus::PUBLISHED)->where('ends_at', '<=', now())->each(function (Trip $trip) {
            DB::transaction(function () use ($trip) {
                $updated = Trip::query()->whereKey($trip->id)->where('status', TripStatus::PUBLISHED)->update(['status' => TripStatus::FINISHED]);
                if ($updated === 0) {
                    return; // another run got here first
                }
                $joined = $trip->joinedCount();
                if ($joined > 0) {
                    $this->records->bump($trip->organiser_id, 'trips_run');
                    $this->records->bump($trip->organiser_id, 'campers_joined', $joined);
                }
                event(new TripFinished($trip->id));
            });
        });

        // The day-before reminder. Claimed with a conditional update so two overlapping runs
        // cannot both send it.
        Trip::query()
            ->where('status', TripStatus::PUBLISHED)
            ->whereNull('reminded_at')
            ->whereBetween('starts_at', [
                now()->addHours((int) config('trips.reminder_from_hours')),
                now()->addHours((int) config('trips.reminder_to_hours')),
            ])
            ->each(function (Trip $trip) {
                if (Trip::query()->whereKey($trip->id)->whereNull('reminded_at')->update(['reminded_at' => now()]) === 1) {
                    event(new TripReminderDue($trip->id));
                }
            });
    }
}
