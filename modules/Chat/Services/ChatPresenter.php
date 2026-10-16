<?php

namespace Modules\Chat\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Modules\Identity\Contracts\People;
use Modules\Shared\Support\Iso;
use Modules\Trips\Contracts\TripRequests;

/** Wire shapes: Conversation and ChatMessage in the app's src/api/types.ts. */
final class ChatPresenter
{
    public function __construct(private readonly People $people, private readonly TripRequests $requests) {}

    /** A message with its sender's first name, in party conversations (null otherwise).
     *
     * @return array<string, mixed>
     */
    public function messageIn(Conversation $c, Message $m): array
    {
        return $this->messages($c, [$m])[0];
    }

    /**
     * @param  list<Message>  $messages
     * @return list<array<string, mixed>>
     */
    public function messages(Conversation $c, array $messages): array
    {
        $names = [];
        if ($messages !== [] && $this->isParty($c)) {
            $ids = array_values(array_unique(array_filter(array_map(fn (Message $m) => $m->sender_id, $messages))));
            $names = $this->people->publicProfiles($ids);
        }

        return array_map(fn (Message $m) => $this->message($m, $names === [] || $m->sender_id === null ? null : (string) ($names[$m->sender_id]['firstName'] ?? '')), $messages);
    }

    private function isParty(Conversation $c): bool
    {
        return $c->request_id !== null && ($this->requests->context([$c->request_id])[$c->request_id]['partyId'] ?? null) !== null;
    }

    /** @return array<string, mixed> */
    public function message(Message $m, ?string $senderName = null): array
    {
        return [
            'id' => $m->id,
            'clientId' => $m->client_id,
            'conversationId' => $m->conversation_id,
            'senderId' => $m->sender_id,
            'senderName' => $senderName,
            'kind' => $m->kind,
            'body' => $m->body,
            'photo' => $m->photo_hash === null ? null : ['hash' => $m->photo_hash],
            'location' => $m->kind === 'location' ? ['lat' => $m->lat, 'lng' => $m->lng, 'accuracyM' => $m->accuracy_m] : null,
            'system' => $m->kind === 'system' ? ['key' => $m->system_key, 'params' => (object) ($m->system_params ?? [])] : null,
            'createdAt' => Iso::format($m->created_at),
        ];
    }

    /**
     * @param  Collection<int, Conversation>  $rows
     * @return list<array<string, mixed>>
     */
    public function conversations(Collection $rows, string $viewerId): array
    {
        $context = $this->requests->context($rows->pluck('request_id')->filter()->values()->all());
        $others = $rows->flatMap(fn (Conversation $c) => [$this->other($c, $viewerId), $c->camper_id, $c->organiser_id])->filter()->unique()->values()->all();
        $profiles = $this->people->publicProfiles($others);
        $lastIds = Message::query()->whereIn('conversation_id', $rows->modelKeys())
            ->groupBy('conversation_id')->select('conversation_id', DB::raw('max(id) as id'))->pluck('id');
        $last = Message::query()->whereIn('id', $lastIds)->get()->keyBy('conversation_id');
        $unread = $this->unread($rows->modelKeys(), $viewerId);

        $party = fn (Conversation $c) => ($context[$c->request_id ?? '']['partyId'] ?? null) === null ? null : [
            'leaderName' => (string) ($profiles[$c->camper_id ?? '']['firstName'] ?? ''),
            'organiserName' => (string) ($profiles[$c->organiser_id ?? '']['firstName'] ?? ''),
        ];

        return $rows->map(fn (Conversation $c) => [
            'id' => $c->id,
            'requestId' => $c->request_id,
            'tripId' => $c->trip_id,
            'trip' => ['title' => $c->trip_title, 'kind' => $c->trip_kind, 'startsAt' => Iso::format($c->trip_starts_at)],
            'role' => $viewerId === $c->organiser_id ? 'organiser' : ($viewerId === $c->camper_id ? 'camper' : 'member'),
            'party' => $party($c),
            'other' => $profiles[$this->other($c, $viewerId) ?? ''] ?? null,
            'requestStatus' => $context[$c->request_id ?? '']['requestStatus'] ?? null,
            'closesAt' => $c->closes_at === null ? null : Iso::format($c->closes_at),
            'lastMessage' => isset($last[$c->id]) ? $this->message($last[$c->id]) : null,
            'unread' => $unread[$c->id] ?? 0,
            'updatedAt' => Iso::format($c->updated_at),
        ])->values()->all();
    }

    /** The organiser for the camper side and for readers; the camper for the organiser. */
    private function other(Conversation $c, string $viewerId): ?string
    {
        return $viewerId === $c->organiser_id ? $c->camper_id : $c->organiser_id;
    }

    /**
     * Messages after the viewer's last read that the viewer neither sent nor caused.
     *
     * @param  list<string>  $conversationIds
     * @return array<string, int>
     */
    public function unread(array $conversationIds, string $viewerId): array
    {
        if ($conversationIds === []) {
            return [];
        }

        return DB::table('messages as m')
            ->leftJoin('conversation_reads as r', fn ($join) => $join->on('r.conversation_id', '=', 'm.conversation_id')->where('r.user_id', $viewerId))
            ->whereIn('m.conversation_id', $conversationIds)
            ->where(fn ($q) => $q->whereNull('r.last_read_id')->orWhereColumn('m.id', '>', 'r.last_read_id'))
            ->where(fn ($q) => $q->whereNull('m.sender_id')->orWhere('m.sender_id', '!=', $viewerId))
            ->where(fn ($q) => $q->whereNull('m.actor_id')->orWhere('m.actor_id', '!=', $viewerId))
            ->groupBy('m.conversation_id')
            ->select('m.conversation_id', DB::raw('count(*) as n'))
            ->pluck('n', 'conversation_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
