<?php

namespace Modules\Chat\Listeners;

use Modules\Chat\Models\Conversation;
use Modules\Chat\Services\Conversations;
use Modules\Shared\Events\TripEdited;
use Modules\Trips\Contracts\TripRequests;

/**
 * The meetup changed: a line in every open conversation of the trip. The bound time uses the
 * model's millisecond format: SQLite compares these columns as text.
 */
final class NoteTripEdit
{
    public function __construct(private readonly Conversations $conversations, private readonly TripRequests $requests) {}

    public function handle(TripEdited $event): void
    {
        $trip = $this->requests->trip($event->tripId);
        if ($trip === null) {
            return;
        }
        Conversation::query()
            ->where('trip_id', $event->tripId)
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()->format('Y-m-d H:i:s.v')))
            ->each(fn (Conversation $c) => $this->conversations->system($c, 'trip_edited', ['meetupAt' => $trip['meetupAt']], $event->organiserId));
    }
}
