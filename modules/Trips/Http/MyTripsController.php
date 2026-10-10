<?php

namespace Modules\Trips\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Shared\Support\Iso;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Models\TripRequestMember;
use Modules\Trips\Services\HostedRequests;
use Modules\Trips\Services\TripPresenter;
use Modules\Trips\Support\RequestStatus;

/** GET /me/trips: everything the phone saves for offline use. */
final class MyTripsController
{
    public function __invoke(Request $request, TripPresenter $presenter, HostedRequests $hosted): JsonResponse
    {
        $userId = (string) $request->user()->getAuthIdentifier();
        $since = now()->subDays((int) config('trips.details_days'));

        $goingTrips = Trip::query()
            ->whereIn('id', TripRequest::query()
                ->whereIn('id', TripRequestMember::query()->where('user_id', $userId)->select('request_id'))
                ->where(fn ($q) => $q->whereIn('status', RequestStatus::OPEN)->orWhere('ended_at', '>', $since))
                ->select('trip_id'))
            ->where('ends_at', '>', $since)
            ->orderBy('starts_at')->get();

        $hostingTrips = Trip::query()->where('organiser_id', $userId)->where('ends_at', '>', $since)->orderBy('starts_at')->get();

        return new JsonResponse([
            'going' => $goingTrips->map(fn (Trip $trip) => $presenter->detail($trip, $userId))->values()->all(),
            'hosting' => $hostingTrips->map(fn (Trip $trip) => [
                'trip' => $presenter->detail($trip, $userId),
                'requests' => $hosted->for($trip),
            ])->values()->all(),
            'serverTime' => Iso::format(now()),
        ]);
    }
}
