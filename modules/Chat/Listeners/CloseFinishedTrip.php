<?php

namespace Modules\Chat\Listeners;

use Carbon\CarbonImmutable;
use Modules\Chat\Models\Conversation;
use Modules\Shared\Events\TripFinished;
use Modules\Trips\Contracts\TripRequests;

/** Joined people may still talk for a week after the trip (lost items, photos). */
final class CloseFinishedTrip
{
    public function __construct(private readonly TripRequests $requests) {}

    public function handle(TripFinished $event): void
    {
        $trip = $this->requests->trip($event->tripId);
        if ($trip === null) {
            return;
        }
        $joined = array_column($this->requests->requestsOfTrip($event->tripId, ['joined']), 'requestId');
        Conversation::query()->whereIn('request_id', $joined)->update([
            'closes_at' => CarbonImmutable::parse($trip['endsAt'])->addDays((int) config('chat.joined_open_days')),
            'updated_at' => now()->format('Y-m-d H:i:s.v'),
        ]);
    }
}
