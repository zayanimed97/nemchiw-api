<?php

namespace Modules\Chat\Services;

use Modules\Chat\Contracts\ConversationDirectory;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\ConversationRead;

final class EloquentConversationDirectory implements ConversationDirectory
{
    public function idForRequest(string $requestId): ?string
    {
        return Conversation::query()->where('request_id', $requestId)->value('id');
    }

    public function isViewing(string $conversationId, string $userId): bool
    {
        return ConversationRead::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->where('viewing_until', '>', now())
            ->exists();
    }
}
