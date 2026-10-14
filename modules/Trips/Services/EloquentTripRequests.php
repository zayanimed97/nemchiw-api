<?php

namespace Modules\Trips\Services;

use Modules\Shared\Support\Iso;
use Modules\Trips\Contracts\TripRequests;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripRequest;

final class EloquentTripRequests implements TripRequests
{
    public function context(array $requestIds): array
    {
        if ($requestIds === []) {
            return [];
        }

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
        ];
    }

    public function requestsOfTrip(string $tripId, array $statuses): array
    {
        return TripRequest::query()->where('trip_id', $tripId)->whereIn('status', $statuses)->orderBy('id')->get()
            ->map(fn (TripRequest $r) => ['requestId' => $r->id, 'leaderId' => $r->leader_id, 'status' => $r->status])
            ->values()
            ->all();
    }
}
