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
}
