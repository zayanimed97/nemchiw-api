<?php

namespace Modules\Trips\Services;

use Modules\Identity\Contracts\People;
use Modules\Shared\Support\Iso;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Models\TripRequestMember;
use Modules\Trips\Support\RequestStatus;

/** The organiser's view of the people asking to join a trip. */
final class HostedRequests
{
    public function __construct(private readonly People $people, private readonly TripPresenter $presenter) {}

    /** @return list<array<string, mixed>> */
    public function for(Trip $trip): array
    {
        $requests = TripRequest::query()->with('members')->where('trip_id', $trip->id)
            ->whereIn('status', RequestStatus::OPEN)->orderBy('created_at')->get();
        $profiles = $this->people->publicProfiles($requests->pluck('leader_id')->all());
        $detailsOpen = now()->lessThan($trip->ends_at->copy()->addDays((int) config('trips.details_days')));

        return $requests->map(fn (TripRequest $r) => [
            'id' => $r->id,
            'status' => $r->status,
            'note' => $r->note,
            'createdAt' => Iso::format($r->created_at),
            'acceptedAt' => $r->accepted_at === null ? null : Iso::format($r->accepted_at),
            'joinedAt' => $r->joined_at === null ? null : Iso::format($r->joined_at),
            'leader' => $profiles[$r->leader_id] ?? null,
            'history' => $this->history($r->leader_id),
            'members' => $r->members->map(fn (TripRequestMember $m) => [
                'userId' => $m->user_id,
                'scannedAt' => $m->scanned_at === null ? null : Iso::format($m->scanned_at),
            ])->values()->all(),
            'codeSecret' => $r->status === RequestStatus::ACCEPTED ? $r->code_secret : null,
            'report' => $this->presenter->reportBy($r->id, $trip->organiser_id),
            'details' => $r->status === RequestStatus::JOINED && $detailsOpen ? $this->people->participantDetails($r->leader_id) : null,
        ])->values()->all();
    }

    /** @return array{tripsJoined: int, withdrawals: array{pending: int, accepted: int, joined: int}} */
    public function history(string $userId): array
    {
        $withdrawals = TripRequest::query()->where('leader_id', $userId)->where('status', RequestStatus::WITHDRAWN)
            ->groupBy('withdrawn_from')->selectRaw('withdrawn_from, count(*) as n')->pluck('n', 'withdrawn_from');

        return [
            'tripsJoined' => TripRequest::query()->where('leader_id', $userId)->where('status', RequestStatus::JOINED)->count(),
            'withdrawals' => [
                'pending' => (int) ($withdrawals['pending'] ?? 0),
                'accepted' => (int) ($withdrawals['accepted'] ?? 0),
                'joined' => (int) ($withdrawals['joined'] ?? 0),
            ],
        ];
    }
}
