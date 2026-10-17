<?php

namespace Modules\Trips\Services;

use Modules\Shared\Support\Iso;
use Modules\Trips\Actions\RequestTransitions;
use Modules\Trips\Contracts\TripRequests;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Models\TripRequestMember;

final class EloquentTripRequests implements TripRequests
{
    public function __construct(private readonly RequestTransitions $transitions) {}

    public function context(array $requestIds): array
    {
        if ($requestIds === []) {
            return [];
        }

        $members = TripRequestMember::query()->whereIn('request_id', $requestIds)->orderBy('user_id')->get(['request_id', 'user_id'])
            ->groupBy('request_id')->map(fn ($rows) => $rows->pluck('user_id')->map(fn ($id) => (string) $id)->values()->all());

        return TripRequest::query()->with('trip')->whereIn('id', $requestIds)->get()
            ->mapWithKeys(fn (TripRequest $r) => [$r->id => [
                'requestId' => $r->id,
                'tripId' => $r->trip_id,
                'tripTitle' => $r->trip->title,
                'tripKind' => $r->trip->kind,
                'startsAt' => Iso::format($r->trip->starts_at),
                'endsAt' => Iso::format($r->trip->ends_at),
                'meetupAt' => Iso::format($r->trip->meetup_at),
                'tripStatus' => $r->trip->status,
                'requestStatus' => $r->status,
                'leaderId' => $r->leader_id,
                'organiserId' => $r->trip->organiser_id,
                'memberIds' => $members[$r->id] ?? [],
                'partyId' => $r->party_id === null ? null : (string) $r->party_id,
            ]])
            ->all();
    }

    public function trip(string $tripId): ?array
    {
        $trip = Trip::query()->find($tripId);

        return $trip === null ? null : [
            'tripId' => $trip->id,
            'title' => $trip->title,
            'kind' => $trip->kind,
            'organiserId' => $trip->organiser_id,
            'startsAt' => Iso::format($trip->starts_at),
            'endsAt' => Iso::format($trip->ends_at),
            'meetupAt' => Iso::format($trip->meetup_at),
            'status' => $trip->status,
            'seats' => $trip->seats,
            'joined' => $trip->joinedCount(),
            'womenOnly' => $trip->women_only,
            'place' => (string) ($trip->destination['name']['en'] ?? collect($trip->destination['name'] ?? [])->first() ?? $trip->title),
        ];
    }

    public function requestsOfTrip(string $tripId, array $statuses): array
    {
        return TripRequest::query()->where('trip_id', $tripId)->whereIn('status', $statuses)->orderBy('id')->get()
            ->map(fn (TripRequest $r) => ['requestId' => $r->id, 'leaderId' => $r->leader_id, 'status' => $r->status])
            ->values()
            ->all();
    }

    public function blockingRequestFor(string $tripId, string $userId): ?string
    {
        $id = $this->transitions->blockingRequests($tripId, [$userId])->orderBy('id')->value('id');

        return $id === null ? null : (string) $id;
    }

    public function removePartyMember(string $requestId, string $userId, string $by): void
    {
        $this->transitions->removeMember($requestId, $userId, $by);
    }

    public function changeLeader(string $requestId, string $leaderId): void
    {
        $this->transitions->changeLeader($requestId, $leaderId);
    }

    public function partyIdOf(string $requestId): ?string
    {
        $id = TripRequest::query()->whereKey($requestId)->value('party_id');

        return $id === null ? null : (string) $id;
    }

    public function withdrawForParty(string $requestId): void
    {
        $this->transitions->withdrawForParty($requestId);
    }
}
