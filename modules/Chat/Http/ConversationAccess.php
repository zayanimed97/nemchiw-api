<?php

namespace Modules\Chat\Http;

use Illuminate\Http\Request;
use Modules\Chat\Models\Conversation;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

/** A conversation only its two people may touch. */
final class ConversationAccess
{
    public static function for(Request $request, string $id): Conversation
    {
        $conversation = Conversation::query()->findOrFail(strtolower($id));
        if (! $conversation->isParticipant(self::userId($request))) {
            throw new ApiException(ApiErrorCode::Forbidden, 'This is not your conversation');
        }

        return $conversation;
    }

    public static function userId(Request $request): string
    {
        return (string) $request->user()->getAuthIdentifier();
    }
}
