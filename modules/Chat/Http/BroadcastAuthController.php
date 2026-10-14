<?php

namespace Modules\Chat\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Chat\Realtime\Realtime;

/** POST /broadcasting/auth: pusher-js asks before subscribing to a private channel. */
final class BroadcastAuthController
{
    private const PREFIX = 'private-conversation.';

    public function __invoke(Request $request, Realtime $realtime): JsonResponse
    {
        $data = $request->validate([
            'socketId' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'channelName' => ['required', 'string', 'regex:/^private-conversation\.[0-9A-Za-z]{26}$/'],
        ]);
        ConversationAccess::for($request, substr($data['channelName'], strlen(self::PREFIX)));

        return new JsonResponse($realtime->authorize($data['channelName'], $data['socketId']));
    }
}
