<?php

namespace Modules\Spots\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Media\Contracts\PlacePhotos;
use Modules\Shared\Places\PlaceKind;
use Modules\Shared\Places\SyncFeed;
use Modules\Spots\Models\Spot;

final class SpotsController
{
    public function __invoke(Request $request, PlacePhotos $photos): JsonResponse
    {
        return SyncFeed::respond($request, Spot::withTrashed(), function (Collection $spots) use ($request, $photos) {
            $hashes = $photos->hashesFor(PlaceKind::SPOT, $spots->modelKeys());

            return $spots->map(fn (Spot $spot) => (new SpotResource($spot, $hashes[$spot->id] ?? []))->resolve($request))->all();
        });
    }
}
