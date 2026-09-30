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
        // Taken before the query so a row written during it is not skipped next time.
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

        // The cursor is never earlier than the newest row sent: ImportSpots stamps rows
        // a few milliseconds apart, which can put them just ahead of the clock.
        $cursor = $spots->isEmpty() || (! $full && $spots->last()->updated_at->lessThan($now))
            ? $now
            : $spots->last()->updated_at;

        return new JsonResponse([
            'data' => SpotResource::collection($spots)->resolve(),
            'serverTime' => Iso::format($cursor),
        ]);
    }
}
