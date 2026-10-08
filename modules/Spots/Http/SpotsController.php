<?php

namespace Modules\Spots\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Shared\Places\SyncFeed;
use Modules\Spots\Models\Spot;

final class SpotsController
{
    public function __invoke(Request $request): JsonResponse
    {
        return SyncFeed::respond(
            $request,
            Spot::withTrashed(),
            fn (Collection $spots) => $spots->map(fn (Spot $spot) => (new SpotResource($spot))->resolve($request))->all(),
        );
    }
}
