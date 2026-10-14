<?php

namespace Modules\Chat\Contracts;

/** What Push needs from Chat: where a request's conversation is, and who is looking at it. */
interface ConversationDirectory
{
    public function idForRequest(string $requestId): ?string;

    /** True while the person has this conversation open on their phone. */
    public function isViewing(string $conversationId, string $userId): bool;
}
