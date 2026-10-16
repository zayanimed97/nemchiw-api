<?php

namespace Modules\Chat\Http;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Services\ChatPresenter;
use Modules\Shared\Support\Iso;

/** GET /me/conversations: the Messages list, sync-style. Conversations are never deleted. */
final class ConversationsController
{
    public function __construct(private readonly ChatPresenter $presenter) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['updatedSince' => ['sometimes', 'date']]);
        $userId = ConversationAccess::userId($request);
        $now = CarbonImmutable::now();
        $since = isset($data['updatedSince']) ? CarbonImmutable::parse($data['updatedSince'])->utc() : null;
        $full = $since === null || $since->lt($now->subDays((int) config('chat.full_after_days')));

        $rows = Conversation::query()
            ->where(fn ($q) => $q->where('camper_id', $userId)->orWhere('organiser_id', $userId)
                ->orWhereIn('id', DB::table('conversation_readers')->where('user_id', $userId)->select('conversation_id')))
            // Bound as a string: the default binding format drops milliseconds.
            ->when(! $full, fn ($q) => $q->where('updated_at', '>', $since->format('Y-m-d H:i:s.v')))
            ->orderBy('updated_at')
            ->get();

        return new JsonResponse([
            'data' => $this->presenter->conversations($rows, $userId),
            'full' => $full,
            // A little in the past: a write that committed during this read is sent next time.
            'serverTime' => Iso::format($now->subSeconds((int) config('chat.cursor_lag_seconds'))),
        ]);
    }
}
