<?php

namespace Modules\Chat\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Services\Conversations;
use Modules\Shared\Events\RequestChanged;
use Modules\Trips\Contracts\TripRequests;

/**
 * Keeps who may see a party's conversation in step with its request: a person who leaves or is
 * removed loses access, and when the lead moves the right to write moves with it (the old
 * leader keeps reading if they are still a member). Runs after FollowRequest, which reads the old leader for its line.
 */
final class FollowParty
{
    public function __construct(private readonly TripRequests $requests, private readonly Conversations $conversations) {}

    public function handle(RequestChanged $event): void
    {
        if (! in_array($event->change, ['member_left', 'member_removed', 'leader_changed'], true)) {
            return;
        }
        $conversation = Conversation::query()->where('request_id', $event->requestId)->first();
        if ($conversation === null) {
            return;
        }
        if ($event->change === 'leader_changed') {
            $old = $conversation->camper_id;
            $conversation->forceFill(['camper_id' => $event->leaderId])->save();
            DB::table('conversation_readers')->where('conversation_id', $conversation->id)->where('user_id', $event->leaderId)->delete();
            $members = $this->requests->context([$event->requestId])[$event->requestId]['memberIds'] ?? [];
            if ($old !== null && in_array($old, $members, true)) {
                DB::table('conversation_readers')->insertOrIgnore(['conversation_id' => $conversation->id, 'user_id' => $old]);
            }

            return;
        }
        if ($event->note !== null) {
            DB::table('conversation_readers')->where('conversation_id', $conversation->id)->where('user_id', $event->note)->delete();
            $this->conversations->announce($conversation, 'member.removed', ['userId' => $event->note]);
        }
    }
}
