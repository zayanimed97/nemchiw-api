<?php

namespace Modules\Trails\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Media\Contracts\PlacePhotos;
use Modules\Shared\Places\PlaceKind;
use Modules\Shared\Places\SyncFeed;
use Modules\Trails\Models\Trail;

final class TrailsController
{
    public function __invoke(Request $request, PlacePhotos $photos): JsonResponse
    {
        return SyncFeed::respond($request, Trail::withTrashed(), function (Collection $trails) use ($request, $photos) {
            $hashes = $photos->hashesFor(PlaceKind::TRAIL, $trails->modelKeys());

            return $trails->map(fn (Trail $trail) => (new TrailResource($trail, $hashes[$trail->id] ?? []))->resolve($request))->all();
        });
    }
}
