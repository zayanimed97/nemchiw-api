<?php

namespace Modules\Chat\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Modules\Chat\Models\Message;
use Modules\Chat\Services\ChatPresenter;
use Modules\Chat\Services\Conversations;
use Modules\Media\Contracts\ChatPhotos;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

final class MessagesController
{
    public function __construct(
        private readonly Conversations $conversations,
        private readonly ChatPresenter $presenter,
        private readonly ChatPhotos $photos,
    ) {}

    /** Oldest first. No cursor: the latest page. `after`: newer than; `before`: older than. */
    public function index(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'after' => ['sometimes', 'string', 'size:26'],
            'before' => ['sometimes', 'string', 'size:26'],
        ]);
        $conversation = ConversationAccess::for($request, $id);
        $size = (int) config('chat.page_size');
        $query = Message::query()->where('conversation_id', $conversation->id);

        if (isset($data['after'])) {
            $rows = $query->where('id', '>', strtolower($data['after']))->orderBy('id')->limit($size + 1)->get();
            $more = $rows->count() > $size;
            $rows = $rows->take($size);
        } else {
            if (isset($data['before'])) {
                $query->where('id', '<', strtolower($data['before']));
            }
            $rows = $query->orderByDesc('id')->limit($size + 1)->get();
            $more = $rows->count() > $size;
            $rows = $rows->take($size)->reverse();
        }

        return new JsonResponse([
            'data' => $rows->values()->map(fn (Message $m) => $this->presenter->message($m))->all(),
            'hasMore' => $more,
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $conversation = ConversationAccess::for($request, $id);
        $userId = ConversationAccess::userId($request);
        $data = $request->validate([
            'clientId' => ['required', 'uuid'],
            'kind' => ['required', Rule::in(['text', 'location', 'photo'])],
            'body' => [Rule::requiredIf(fn () => $request->input('kind') === 'text'), 'nullable', 'string'],
            'lat' => ['required_if:kind,location', 'numeric', 'between:-90,90'],
            'lng' => ['required_if:kind,location', 'numeric', 'between:-180,180'],
            'accuracyM' => ['nullable', 'integer', 'between:0,65535'],
            'photo' => ['required_if:kind,photo', 'file', 'max:'.(int) config('chat.photo_max_kb')],
        ]);
        if (mb_strlen((string) ($data['body'] ?? '')) > (int) config('chat.message_max')) {
            throw new ApiException(ApiErrorCode::MessageTooLong, 'Messages are at most '.config('chat.message_max').' characters');
        }

        // A repeat (the phone retried after a lost answer) costs nothing: no photo work, no limits.
        $existing = Message::query()->where('sender_id', $userId)->where('client_id', $data['clientId'])->first();
        if ($existing !== null) {
            return new JsonResponse($this->presenter->message($existing), 200);
        }

        $hash = null;
        if ($data['kind'] === 'photo') {
            if ($conversation->isClosed()) {
                throw new ApiException(ApiErrorCode::ConversationClosed, 'This conversation is closed');
            }
            $key = "chat-photos:{$userId}";
            if (RateLimiter::tooManyAttempts($key, (int) config('chat.photos_per_hour'))) {
                throw new ApiException(ApiErrorCode::RateLimited, 'Too many photos. Try again later', RateLimiter::availableIn($key));
            }
            RateLimiter::hit($key, 3600);
            try {
                $hash = $this->photos->store($conversation->id, (string) $request->file('photo')->get());
            } catch (ApiException $e) {
                throw new ApiException(ApiErrorCode::PhotoInvalid, $e->getMessage());
            }
        }

        [$message, $created] = $this->conversations->post($conversation, $userId, $data, $hash);

        return new JsonResponse($this->presenter->message($message), $created ? 201 : 200);
    }
}
