<?php

namespace Modules\Parties\Services;

use Modules\Identity\Contracts\People;
use Modules\Parties\Actions\Parties;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Support\Iso;
use Modules\Trips\Contracts\TripRequests;

/** Party wire shapes. The leader sees every open row (masked contacts included); members see who is in. */
final class PartyPresenter
{
    public function __construct(private readonly People $people, private readonly TripRequests $trips) {}

    /** @return array<string, mixed> */
    public function forViewer(Party $party, string $viewerId): array
    {
        $isLeader = $party->leader_id === $viewerId;
        $states = $isLeader ? [PartyMember::INVITED, PartyMember::IN, PartyMember::DECLINED] : [PartyMember::IN];
        $rows = PartyMember::query()->where('party_id', $party->id)->whereIn('state', $states)
            ->orderBy('invited_at')->orderBy('id')->get();

        $people = $this->people->publicProfiles([$party->leader_id, ...$rows->pluck('user_id')->filter()->all()]);

        return [
            'id' => $party->id,
            'state' => $party->state,
            'leaderId' => $party->leader_id,
            'leader' => $people[$party->leader_id] ?? null,
            'request' => $this->request($party),
            'members' => $rows->map(fn (PartyMember $m) => [
                'id' => $m->id,
                'userId' => $m->user_id,
                'person' => $m->user_id === null ? null : ($people[$m->user_id] ?? null),
                'contact' => $isLeader ? $m->contact_masked : null,
                'state' => $m->state,
                'answeredAt' => $m->answered_at === null ? null : Iso::format($m->answered_at),
                'invitedAt' => Iso::format($m->invited_at ?? $m->created_at),
            ])->all(),
            'createdAt' => Iso::format($party->created_at),
        ];
    }

    /** @return array{id: string, tripId: string, status: string}|null */
    private function request(Party $party): ?array
    {
        if ($party->request_id === null) {
            return null;
        }
        $context = $this->trips->context([$party->request_id])[$party->request_id] ?? null;

        return $context === null ? null : ['id' => $party->request_id, 'tripId' => $context['tripId'], 'status' => $context['requestStatus']];
    }

    /** @return array{party: array<string, mixed>|null, invitations: list<array<string, mixed>>} */
    public function mine(string $userId): array
    {
        $party = Parties::openFor($userId, lock: false);

        $invites = PartyMember::query()->where('user_id', $userId)->where('state', PartyMember::INVITED)
            ->whereIn('party_id', Party::query()->where('state', '!=', Party::OVER)->select('id'))
            ->orderByDesc('invited_at')->get();
        $parties = Party::query()->whereIn('id', $invites->pluck('party_id'))->get()->keyBy('id');
        $inRows = PartyMember::query()->whereIn('party_id', $invites->pluck('party_id'))->where('state', PartyMember::IN)->get()->groupBy('party_id');
        $invitedCounts = PartyMember::query()->whereIn('party_id', $invites->pluck('party_id'))->where('state', PartyMember::INVITED)
            ->selectRaw('party_id, count(*) as n')->groupBy('party_id')->pluck('n', 'party_id');
        $people = $this->people->publicProfiles(array_values(array_unique([
            ...$parties->pluck('leader_id')->all(), ...$inRows->flatten()->pluck('user_id')->filter()->all(),
        ])));

        return [
            'party' => $party === null ? null : $this->forViewer($party, $userId),
            'invitations' => $invites->map(fn (PartyMember $m) => [
                'partyId' => $m->party_id,
                'leader' => $people[$parties[$m->party_id]->leader_id] ?? null,
                'joined' => collect($inRows[$m->party_id] ?? [])->map(fn ($r) => $people[$r->user_id] ?? null)->filter()->values()->all(),
                'invitedCount' => (int) ($invitedCounts[$m->party_id] ?? 0),
                'invitedAt' => Iso::format($m->invited_at ?? $m->created_at),
            ])->filter(fn ($i) => $i['leader'] !== null)->values()->all(),
        ];
    }
}
