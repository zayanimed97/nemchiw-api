<?php

namespace Modules\Chat\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Services\Conversations;
use Modules\Identity\Contracts\People;
use Modules\Shared\Events\RequestChanged;
use Modules\Trips\Contracts\TripRequests;

/** A request opens its conversation: the "asked" line, then the camper's note as a message. */
final class OpenConversation
{
    public function __construct(
        private readonly TripRequests $requests,
        private readonly Conversations $conversations,
        private readonly People $people,
    ) {}

    public function handle(RequestChanged $event): void
    {
        if ($event->change !== 'asked' || Conversation::query()->where('request_id', $event->requestId)->exists()) {
            return;
        }
        $trip = $this->requests->trip($event->tripId);
        if ($trip === null) {
            return;
        }
        $conversation = Conversation::query()->create([
            'request_id' => $event->requestId,
            'trip_id' => $event->tripId,
            'camper_id' => $event->leaderId,
            'organiser_id' => $event->organiserId,
            'trip_title' => Str::limit($trip['title'], 117),
            'trip_kind' => $trip['kind'],
            'trip_starts_at' => CarbonImmutable::parse($trip['startsAt']),
        ]);
        $name = $this->people->publicProfiles([$event->leaderId])[$event->leaderId]['firstName'] ?? '';
        $this->conversations->system($conversation, 'asked', ['name' => (string) $name], $event->leaderId);
        if ($event->note !== null && trim($event->note) !== '') {
            $this->conversations->post($conversation, $event->leaderId, [
                'clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => trim($event->note),
            ], announce: false);
        }
    }
}
