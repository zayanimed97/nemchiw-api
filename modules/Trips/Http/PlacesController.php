<?php

namespace Modules\Trips\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\People;
use Modules\Media\Contracts\PlacePhotos;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Shared\Places\PlaceDirectory;
use Modules\Shared\Places\PlaceStatus;

/** Places organisers add while creating a trip: certified ones go live, community ones wait for review. */
final class PlacesController
{
    public function __construct(private readonly PlaceDirectory $places) {}

    public function store(SubmitPlaceRequest $request, string $kind, People $people, OrganiserRecords $records): JsonResponse
    {
        $userId = (string) $request->user()->getAuthIdentifier();
        if (! $people->tripReadiness($userId)['ready']) {
            throw new ApiException(ApiErrorCode::NotTripReady, 'Finish your trip-ready profile first');
        }
        $live = $records->isCertified($userId);
        $id = $this->places->submit($kind, $request->validated(), $userId, $live);

        return new JsonResponse(['kind' => $kind, 'id' => $id, 'status' => $live ? PlaceStatus::LIVE : PlaceStatus::PENDING], 201);
    }

    public function photo(Request $request, string $kind, string $id, PlacePhotos $photos): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file', 'max:5120']]);
        $place = $this->places->find($kind, $id) ?? abort(404);
        if ($place['createdBy'] !== (string) $request->user()->getAuthIdentifier()) {
            throw new ApiException(ApiErrorCode::Forbidden, 'Only the person who added this place can add photos');
        }
        if (count($photos->hashesFor($kind, [$id])[$id] ?? []) >= PlacePhotos::MAX) {
            throw ValidationException::withMessages(['photo' => 'A place has at most '.PlacePhotos::MAX.' photos']);
        }
        $photos->add($kind, $id, (string) file_get_contents($request->file('photo')->getRealPath()));

        return new JsonResponse(['photos' => $photos->hashesFor($kind, [$id])[$id] ?? []]);
    }
}
