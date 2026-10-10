<?php

namespace Modules\Trips\Http;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Trips\Actions\SaveTrip;
use Modules\Trips\Models\Trip;
use Modules\Trips\Services\TripPresenter;
use Modules\Trips\Support\TripKind;
use Modules\Trips\Support\TripStatus;

final class TripsController
{
    public function __construct(private readonly TripPresenter $presenter) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'kind' => ['sometimes', Rule::in(TripKind::ALL)],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'womenOnly' => ['sometimes', 'boolean'],
            'cursor' => ['sometimes', 'string'],
        ]);
        $size = (int) config('trips.page_size');
        $after = $this->decodeCursor($filters['cursor'] ?? null);
        $trips = Trip::query()
            ->where('status', TripStatus::PUBLISHED)
            ->where('starts_at', '>', now())
            ->when($filters['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('starts_at', '>=', CarbonImmutable::parse($from)->utc()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('starts_at', '<', CarbonImmutable::parse($to)->utc()))
            ->when($request->boolean('womenOnly'), fn ($q) => $q->where('women_only', true))
            ->when($after, fn ($q, $after) => $q->where(fn ($q) => $q->where('starts_at', '>', $after[0])
                ->orWhere(fn ($q) => $q->where('starts_at', $after[0])->where('id', '>', $after[1]))))
            ->orderBy('starts_at')->orderBy('id')
            ->limit($size + 1)
            ->get();
        $more = $trips->count() > $size;
        $trips = $trips->take($size);
        $last = $trips->last();

        return new JsonResponse([
            'data' => $this->presenter->summaries($trips),
            'nextCursor' => $more ? $this->encodeCursor($last) : null,
        ]);
    }

    /**
     * Keyset cursor over the stored start (raw, with milliseconds) and id. Laravel's
     * cursorPaginate drops the milliseconds and repeats the last row of a page.
     */
    private function encodeCursor(Trip $trip): string
    {
        return rtrim(strtr(base64_encode(json_encode([$trip->getRawOriginal('starts_at'), $trip->id])), '+/', '-_'), '=');
    }

    /** @return array{0: string, 1: string}|null */
    private function decodeCursor(?string $cursor): ?array
    {
        $value = $cursor === null ? null : json_decode((string) base64_decode(strtr($cursor, '-_', '+/')), true);

        return is_array($value) && count($value) === 2 && is_string($value[0]) && is_string($value[1]) ? $value : null;
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $viewer = $request->user('sanctum')?->getAuthIdentifier();
        $trip = Trip::query()->findOrFail($id);
        abort_if($trip->status === TripStatus::DRAFT && $trip->organiser_id !== $viewer, 404);

        return new JsonResponse($this->presenter->detail($trip, $viewer === null ? null : (string) $viewer));
    }

    public function store(SaveTripRequest $request, SaveTrip $save): JsonResponse
    {
        $userId = (string) $request->user()->getAuthIdentifier();
        $trip = $save->create($userId, $request->validated());

        return new JsonResponse($this->presenter->detail($trip, $userId), 201);
    }

    public function update(SaveTripRequest $request, string $id, SaveTrip $save): JsonResponse
    {
        $userId = (string) $request->user()->getAuthIdentifier();
        $trip = $save->update(Trip::query()->findOrFail($id), $userId, $request->validated());

        return new JsonResponse($this->presenter->detail($trip, $userId));
    }
}
