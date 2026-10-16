<?php

namespace Modules\Push\Listeners;

use Carbon\CarbonImmutable;
use Modules\Chat\Contracts\ConversationDirectory;
use Modules\Chat\Events\MessagePosted;
use Modules\Identity\Contracts\People;
use Modules\Push\Services\Notice;
use Modules\Push\Services\PushSender;
use Modules\Shared\Events\ApplicationDecided;
use Modules\Shared\Events\PartyChanged;
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
        $members = $this->requests->context([$event->requestId])[$event->requestId]['memberIds'] ?? [];
        $members = $members === [] ? [$event->leaderId] : $members;
        // Every request member hears the news (a party is several people), not only the leader.
        $toCamper = fn (string $title, ?string $body = null, array $extra = []) => array_map(
            fn (string $id) => new Notice($id, $title, array_merge($params, $extra), $body, data: $data, thread: $conversationId),
            $members,
        );
        $toOrganiser = fn (string $title, ?string $body = null) => new Notice($event->organiserId, $title, $params, $body, data: $data, thread: $conversationId);

        $notices = match ($event->change) {
            'asked' => [$toOrganiser('new_request', 'new_request_body')],
            'withdrawn' => [$toOrganiser('withdrawn')],
            'accepted' => $toCamper('accepted', $trip['kind'] === 'hike_community' ? 'accepted_body_free' : 'accepted_body', ['name' => $organiser]),
            'declined' => $toCamper('declined'),
            'cancelled' => $toCamper('cancelled'),
            'scanned' => [...$toCamper('joined_you'), $toOrganiser('joined')],
            'trip_cancelled' => $toCamper('trip_cancelled'),
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
        $open = $this->requests->requestsOfTrip($event->tripId, ['joined', 'accepted']);
        $contexts = $this->requests->context(array_column($open, 'requestId'));
        foreach ($open as $request) {
            $body = $request['status'] === 'joined' ? 'reminder_meetup' : 'reminder_pay';
            $members = $contexts[$request['requestId']]['memberIds'] ?? [];
            foreach ($members === [] ? [$request['leaderId']] : $members as $memberId) {
                $notices[] = new Notice($memberId, 'reminder', $params, $body, data: $data);
            }
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

    /** Invitations and party news. Request-driven changes (`request_*`) are covered by `requestChanged`. */
    public function party(PartyChanged $event): void
    {
        $data = ['type' => 'party', 'partyId' => $event->partyId];
        $notice = fn (string $userId, string $title, array $params = []) => new Notice($userId, $title, $params, data: $data);
        $name = function (?string $id): string {
            return $id === null ? '' : (string) ($this->people->publicProfiles([$id])[$id]['firstName'] ?? '');
        };

        $notices = match ($event->change) {
            'invited' => $event->userId === null ? [] : [$notice($event->userId, 'party_invited', ['name' => $name($event->byId ?? $event->leaderId)])],
            'accepted' => [$notice($event->leaderId, 'party_accepted', ['name' => $name($event->userId)])],
            'declined' => [$notice($event->leaderId, 'party_declined', ['name' => $name($event->userId)])],
            'reminded' => $event->userId === null ? [] : [$notice($event->userId, 'party_reminder', ['name' => $name($event->byId ?? $event->leaderId)])],
            'removed' => $event->userId === null ? [] : [$notice($event->userId, 'party_removed')],
            'leader_changed' => $this->toRemaining($event, 'party_leader_changed', ['name' => $name($event->userId ?? $event->leaderId)], $notice),
            'ended', 'auto_ended' => $this->toRemaining($event, 'party_ended', [], $notice),
            default => [], // left, and the request_* changes
        };
        $this->sender->send($notices);
    }

    /** @return list<Notice> */
    private function toRemaining(PartyChanged $event, string $title, array $params, callable $notice): array
    {
        $ids = array_values(array_diff($event->memberIds, $event->byId === null ? [] : [$event->byId]));

        return array_map(fn (string $id) => $notice($id, $title, $params), $ids);
    }
}
