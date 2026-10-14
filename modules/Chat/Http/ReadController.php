<?php

namespace Modules\Chat\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Chat\Models\ConversationRead;
use Modules\Chat\Models\Message;
use Modules\Chat\Services\ChatPresenter;

/** POST /conversations/{id}/read: the read mark only moves forward; `viewing` silences pushes. */
final class ReadController
{
    public function __invoke(Request $request, string $id, ChatPresenter $presenter): JsonResponse
    {
        $data = $request->validate([
            'lastReadId' => ['nullable', 'string', 'size:26'],
            'viewing' => ['required', 'boolean'],
        ]);
        $conversation = ConversationAccess::for($request, $id);
        $userId = ConversationAccess::userId($request);
        $keys = ['conversation_id' => $conversation->id, 'user_id' => $userId];

        $previous = ConversationRead::query()->where($keys)->value('last_read_id');
        $last = $previous;
        $given = isset($data['lastReadId']) ? strtolower($data['lastReadId']) : null;
        if ($given !== null && ($last === null || strcmp($given, $last) > 0)
            && Message::query()->where('conversation_id', $conversation->id)->whereKey($given)->exists()) {
            $last = $given;
        }
        ConversationRead::query()->updateOrInsert($keys, [
            'last_read_id' => $last,
            'viewing_until' => $data['viewing'] ? now()->addSeconds((int) config('chat.viewing_seconds')) : null,
        ]);
        if ($last !== $previous) {
            $conversation->touch(); // the unread count in my feed changed
        }

        return new JsonResponse(['unread' => $presenter->unread([$conversation->id], $userId)[$conversation->id] ?? 0]);
    }
}
