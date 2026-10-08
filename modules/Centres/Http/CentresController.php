<?php

namespace Modules\Centres\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Centres\Models\Centre;
use Modules\Media\Contracts\PlacePhotos;
use Modules\Shared\Places\PlaceKind;
use Modules\Shared\Places\SyncFeed;

final class CentresController
{
    public function __invoke(Request $request, PlacePhotos $photos): JsonResponse
    {
        return SyncFeed::respond($request, Centre::withTrashed(), function (Collection $centres) use ($request, $photos) {
            $hashes = $photos->hashesFor(PlaceKind::CENTRE, $centres->modelKeys());

            return $centres->map(fn (Centre $centre) => (new CentreResource($centre, $hashes[$centre->id] ?? []))->resolve($request))->all();
        });
    }
}
