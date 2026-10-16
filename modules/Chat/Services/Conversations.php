<?php

namespace Modules\Chat\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Chat\Events\MessagePosted;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Modules\Chat\Realtime\Realtime;
use Modules\Identity\Contracts\People;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

use function Illuminate\Support\defer;

/** Writing into conversations: people's messages and the server's system lines. */
final class Conversations
{
    public function __construct(
        private readonly Realtime $realtime,
        private readonly ChatPresenter $presenter,
        private readonly People $people,
    ) {}

    /**
     * Stores a person's message once per client id. Returns [message, created]; a repeat
     * of an already stored client id returns it with created = false, even if the
     * conversation has closed since.
     *
     * @param  array{clientId: string, kind: string, body?: ?string, lat?: float, lng?: float, accuracyM?: ?int}  $input
     * @return array{0: Message, 1: bool}
     */
    public function post(Conversation $conversation, string $senderId, array $input, ?string $photoHash = null, bool $announce = true): array
    {
        $existing = Message::query()->where('sender_id', $senderId)->where('client_id', $input['clientId'])->first();
        if ($existing !== null) {
            return [$existing, false];
        }
        if ($conversation->isClosed()) {
            throw new ApiException(ApiErrorCode::ConversationClosed, 'This conversation is closed');
        }

        $message = DB::transaction(function () use ($conversation, $senderId, $input, $photoHash, $announce) {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $senderId,
                'client_id' => $input['clientId'],
                'kind' => $input['kind'],
                'body' => $input['body'] ?? null,
                'photo_hash' => $photoHash,
                'lat' => $input['kind'] === 'location' ? $input['lat'] : null,
                'lng' => $input['kind'] === 'location' ? $input['lng'] : null,
                'accuracy_m' => $input['kind'] === 'location' ? ($input['accuracyM'] ?? null) : null,
            ]);
            $this->touch($conversation, $message);
            if (! $announce) {
                return $message;
            }
            $name = $this->people->publicProfiles([$senderId])[$senderId]['firstName'] ?? null;
            event(new MessagePosted(
                $message->id, $conversation->id, $senderId, array_values(array_filter($conversation->participantIds(), fn ($id) => $id !== $senderId)),
                $message->kind, $message->body, $conversation->trip_title, $name,
            ));

            return $message;
        });
        $this->broadcast($conversation, $message);

        return [$message, true];
    }

    /** @param array<string, string> $params */
    public function system(Conversation $conversation, string $key, array $params, ?string $actorId): Message
    {
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'actor_id' => $actorId,
            'kind' => 'system',
            'system_key' => $key,
            'system_params' => $params,
        ]);
        $this->touch($conversation, $message);
        $this->broadcast($conversation, $message);

        return $message;
    }

    private function touch(Conversation $conversation, Message $message): void
    {
        $conversation->forceFill(['last_message_at' => $message->created_at])->save();
    }

    /** After the response: a slow or failing Ably never holds up or fails the request. */
    private function broadcast(Conversation $conversation, Message $message): void
    {
        $payload = $this->presenter->messageIn($conversation, $message);
        defer(function () use ($payload) {
            try {
                $this->realtime->publish("private-conversation.{$payload['conversationId']}", 'message.created', $payload);
            } catch (\Throwable $e) {
                Log::warning('chat.broadcast_failed', ['conversation' => $payload['conversationId'], 'error' => $e->getMessage()]);
            }
        });
    }
}
