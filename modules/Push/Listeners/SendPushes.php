<?php

namespace Modules\Push\Listeners;

use Carbon\CarbonImmutable;
use Modules\Chat\Contracts\ConversationDirectory;
use Modules\Chat\Events\MessagePosted;
use Modules\Identity\Contracts\People;
use Modules\Push\Services\Notice;
use Modules\Push\Services\PushSender;
use Modules\Shared\Events\ApplicationDecided;
use Modules\Shared\Events\ReportResolved;
use Modules\Shared\Events\RequestChanged;
use Modules\Shared\Events\TripReminderDue;
use Modules\Shared\Places\PlaceModerated;
use Modules\Trips\Contracts\TripRequests;

/** Who hears about what. Texts live in lang/{ar,fr,en}/push.php. */
final class SendPushes
{
    public function __construct(
        private readonly PushSender $sender,
        private readonly ConversationDirectory $conversations,
        private readonly TripRequests $requests,
        private readonly People $people,
    ) {}

    public function message(MessagePosted $event): void
    {
        $notices = [];
        foreach ($event->recipientIds as $recipientId) {
            if ($this->conversations->isViewing($event->conversationId, $recipientId)) {
                continue;
            }
            $notices[] = new Notice(
                $recipientId,
                'message_title',
                ['name' => $event->senderName ?? '', 'trip' => $event->tripTitle],
                body: match ($event->kind) {
                    'photo' => 'photo',
                    'location' => 'location',
                    default => null,
                },
                rawBody: $event->kind === 'text' ? $event->body : null,
                data: ['type' => 'message', 'conversationId' => $event->conversationId],
                thread: $event->conversationId,
            );
        }
        $this->sender->send($notices);
    }

    public function requestChanged(RequestChanged $event): void
    {
        $trip = $this->requests->trip($event->tripId);
        if ($trip === null) {
            return;
        }
        $conversationId = $this->conversations->idForRequest($event->requestId);
        $data = $conversationId !== null
            ? ['type' => 'message', 'conversationId' => $conversationId]
            : ['type' => 'trip', 'tripId' => $event->tripId];
        $names = $this->people->publicProfiles([$event->leaderId, $event->organiserId]);
        $camper = (string) ($names[$event->leaderId]['firstName'] ?? '');
        $organiser = (string) ($names[$event->organiserId]['firstName'] ?? '');
        $params = ['trip' => $trip['title'], 'name' => $camper];
        $toCamper = fn (string $title, ?string $body = null, array $extra = []) => new Notice($event->leaderId, $title, array_merge($params, $extra), $body, data: $data, thread: $conversationId);
        $toOrganiser = fn (string $title, ?string $body = null) => new Notice($event->organiserId, $title, $params, $body, data: $data, thread: $conversationId);

        $notices = match ($event->change) {
            'asked' => [$toOrganiser('new_request', 'new_request_body')],
            'withdrawn' => [$toOrganiser('withdrawn')],
            'accepted' => [$toCamper('accepted', $trip['kind'] === 'hike_community' ? 'accepted_body_free' : 'accepted_body', ['name' => $organiser])],
            'declined' => [$toCamper('declined')],
            'cancelled' => [$toCamper('cancelled')],
            'scanned' => [$toCamper('joined_you'), $toOrganiser('joined')],
            'trip_cancelled' => [$toCamper('trip_cancelled')],
            default => [], // lapsed, closed: the trip started, or the reporter hears through ReportResolved
        };
        $this->sender->send($notices);
    }

    public function reminder(TripReminderDue $event): void
    {
        $trip = $this->requests->trip($event->tripId);
        if ($trip === null) {
            return;
        }
        $time = CarbonImmutable::parse($trip['meetupAt'])->setTimezone((string) config('push.timezone'))->format('H:i');
        $params = ['trip' => $trip['title'], 'time' => $time];
        $data = ['type' => 'trip', 'tripId' => $event->tripId];
        $notices = [new Notice($trip['organiserId'], 'reminder', $params, 'reminder_meetup', data: $data)];
        foreach ($this->requests->requestsOfTrip($event->tripId, ['joined', 'accepted']) as $request) {
            $body = $request['status'] === 'joined' ? 'reminder_meetup' : 'reminder_pay';
            $notices[] = new Notice($request['leaderId'], 'reminder', $params, $body, data: $data);
        }
        $this->sender->send($notices);
    }

    public function application(ApplicationDecided $event): void
    {
        $this->sender->send([new Notice($event->userId, "application_{$event->outcome}", data: ['type' => 'organiser'])]);
    }

    public function place(PlaceModerated $event): void
    {
        if ($event->createdBy === null) {
            return;
        }
        $title = $event->status === 'live' ? 'place_live' : 'place_rejected';
        $this->sender->send([new Notice($event->createdBy, $title, ['place' => $event->name], data: ['type' => 'place', 'kind' => $event->kind, 'id' => $event->id])]);
    }

    public function report(ReportResolved $event): void
    {
        $trip = $this->requests->trip($event->tripId);
        $conversationId = $this->conversations->idForRequest($event->requestId);
        $data = $conversationId !== null ? ['type' => 'message', 'conversationId' => $conversationId] : ['type' => 'trip', 'tripId' => $event->tripId];
        $this->sender->send([new Notice($event->reporterId, 'report_reviewed', ['trip' => $trip['title'] ?? ''], data: $data)]);
    }
}
