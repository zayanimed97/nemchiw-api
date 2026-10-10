<?php

namespace Modules\Organisers\Http;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Contracts\People;
use Modules\Organisers\Contracts\OrganiserRecords;

/** GET /organisers/{id}: the public organiser card. */
final class OrganiserController
{
    public function __invoke(string $id, People $people, OrganiserRecords $records): JsonResponse
    {
        $profile = $people->publicProfiles([$id])[$id] ?? abort(404);
        $card = $records->cards([$id])[$id];

        return new JsonResponse([
            'id' => $id,
            'firstName' => $profile['firstName'],
            'photo' => $profile['photo'],
            'certified' => $card['certified'],
            'record' => $card['record'],
        ]);
    }
}
