<?php

namespace Modules\Trips\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Trips\Actions\CancelTrip;
use Modules\Trips\Actions\RequestTransitions;
use Modules\Trips\Actions\ScanJoinCode;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Services\TripPresenter;

final class TripRequestsController
{
    public function __construct(private readonly RequestTransitions $transitions, private readonly TripPresenter $presenter) {}

    public function store(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500'], 'partyId' => ['nullable', 'string', 'regex:/^[0-9A-Za-z]{26}$/']]);
        $userId = $this->userId($request);
        $trip = Trip::query()->findOrFail($id);
        if (isset($data['partyId'])) {
            $this->transitions->askAsParty($trip, $userId, strtolower($data['partyId']), $data['note'] ?? null);
        } else {
            $this->transitions->ask($trip, $userId, $data['note'] ?? null);
        }

        return new JsonResponse($this->presenter->detail($trip->fresh(), $userId), 201);
    }

    /** POST /requests/{id}/{accept|decline|withdraw|cancel} */
    public function transition(Request $request, string $id, string $action): JsonResponse
    {
        $userId = $this->userId($request);
        $tripRequest = TripRequest::query()->findOrFail($id);
        $this->transitions->{$action}($tripRequest, $userId);

        return new JsonResponse($this->presenter->detail($tripRequest->trip()->firstOrFail(), $userId));
    }

    public function cancelTrip(Request $request, string $id, CancelTrip $cancel): JsonResponse
    {
        $userId = $this->userId($request);
        $trip = $cancel(Trip::query()->findOrFail($id), $userId);

        return new JsonResponse($this->presenter->detail($trip, $userId));
    }

    public function scan(Request $request, string $id, ScanJoinCode $scan): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'digits:6']]);
        $userId = $this->userId($request);
        $joined = $scan($id, $userId, $data['code']);

        return new JsonResponse($this->presenter->detail($joined->trip()->firstOrFail(), $userId));
    }

    private function userId(Request $request): string
    {
        return (string) $request->user()->getAuthIdentifier();
    }
}
