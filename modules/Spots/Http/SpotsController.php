<?php

namespace Modules\Spots\Http;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Shared\Support\Iso;
use Modules\Spots\Models\Spot;

final class SpotsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['updatedSince' => ['sometimes', 'date']]);
        $pageSize = (int) config('spots.page_size');
        $now = now();

        $spots = Spot::query()
            ->when(isset($validated['updatedSince']), fn ($query) => $query->where(
                'updated_at',
                '>',
                // Bound as a string: the default binding format drops milliseconds.
                CarbonImmutable::parse($validated['updatedSince'])->utc()->format('Y-m-d H:i:s.v'),
            ))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($pageSize + 1)
            ->get();

        $full = $spots->count() > $pageSize;
        $spots = $spots->take($pageSize);

        // A partial page hands back a cursor a little in the past: a write that took
        // its timestamp before this read but committed after it is sent next time
        // instead of skipped. Clients upsert by id, so the overlap costs nothing.
        $cursor = $full
            ? $spots->last()->updated_at
            : $now->subSeconds((int) config('spots.cursor_lag_seconds'));

        return new JsonResponse([
            'data' => SpotResource::collection($spots)->resolve(),
            'serverTime' => Iso::format($cursor),
        ]);
    }
}
