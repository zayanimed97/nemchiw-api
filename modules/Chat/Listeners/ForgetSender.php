<?php

namespace Modules\Chat\Listeners;

use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Modules\Identity\Events\AccountDeleting;
use Modules\Media\Contracts\ChatPhotos;

/**
 * Inside the deletion transaction: the person's photos go, their conversations close.
 * Their words stay for the other side (shown as "Deleted account"); the user row's
 * deletion nulls sender_id, actor_id and the participant columns.
 */
final class ForgetSender
{
    public function __construct(private readonly ChatPhotos $photos) {}

    public function handle(AccountDeleting $event): void
    {
        Message::query()->where('sender_id', $event->userId)->whereNotNull('photo_hash')->each(function (Message $m) {
            $this->photos->delete($m->conversation_id, (string) $m->photo_hash);
            $m->forceFill(['photo_hash' => null])->save();
        });
        Conversation::query()
            ->where(fn ($q) => $q->where('camper_id', $event->userId)->orWhere('organiser_id', $event->userId))
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()->format('Y-m-d H:i:s.v')))
            ->update(['closes_at' => now(), 'updated_at' => now()->format('Y-m-d H:i:s.v')]);
    }
}
