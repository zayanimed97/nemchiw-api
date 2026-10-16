<?php

namespace Modules\Chat\Listeners;

use Modules\Chat\Models\Conversation;
use Modules\Chat\Services\Conversations;
use Modules\Identity\Contracts\People;
use Modules\Shared\Events\RequestChanged;

/** Every later change becomes a system line; an ending closes the conversation. */
final class FollowRequest
{
    public function __construct(private readonly Conversations $conversations, private readonly People $people) {}

    public function handle(RequestChanged $event): void
    {
        if ($event->change === 'asked') {
            return;
        }
        $conversation = Conversation::query()->where('request_id', $event->requestId)->first();
        if ($conversation === null) {
            return; // a request from before 3.3
        }
        if (in_array($event->change, ['member_left', 'member_removed', 'leader_changed'], true)) {
            $this->partyLine($conversation, $event);

            return;
        }
        $actorId = match ($event->by) {
            'camper' => $event->leaderId,
            'organiser' => $event->organiserId,
            default => null,
        };
        $params = [];
        if ($actorId !== null) {
            $params['name'] = (string) ($this->people->publicProfiles([$actorId])[$actorId]['firstName'] ?? '');
        }
        if (in_array($event->change, RequestChanged::ENDINGS, true)) {
            $conversation->forceFill(['closes_at' => now()])->save();
        }
        $this->conversations->system($conversation, $event->change, $params, $actorId);
    }

    /** Party changes name the people involved (`note`), not `by`. Runs before FollowParty moves camper_id. */
    private function partyLine(Conversation $conversation, RequestChanged $event): void
    {
        if ($event->change === 'leader_changed') {
            $ids = array_values(array_filter([$conversation->camper_id, $event->leaderId]));
            $names = $this->people->publicProfiles($ids);
            $name = fn (?string $id) => (string) ($names[$id ?? '']['firstName'] ?? '');
            $this->conversations->system($conversation, 'party_leader_changed', ['from' => $name($conversation->camper_id), 'to' => $name($event->leaderId)], null);

            return;
        }
        $personId = (string) $event->note;
        $name = (string) ($this->people->publicProfiles([$personId])[$personId]['firstName'] ?? '');
        $this->conversations->system(
            $conversation,
            $event->change === 'member_left' ? 'party_member_left' : 'party_member_removed',
            ['name' => $name],
            // A deleted account is gone by now: no actor to point at.
            $event->change === 'member_left' ? ($name === '' ? null : $personId) : $event->leaderId,
        );
    }
}
