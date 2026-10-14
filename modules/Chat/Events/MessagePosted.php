<?php

namespace Modules\Chat\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** A person (not the system) wrote in a conversation. Push tells the other participant. */
final class MessagePosted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $conversationId,
        public readonly string $senderId,
        public readonly ?string $recipientId,
        public readonly string $kind,
        public readonly ?string $body,
        public readonly string $tripTitle,
        public readonly ?string $senderName,
    ) {}
}
