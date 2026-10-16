<?php

namespace Modules\Parties\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Parties\Actions\Friends;

final class FriendsController
{
    public function index(Request $request, Friends $friends): JsonResponse
    {
        return new JsonResponse(['data' => $friends->listFor(PartiesController::userId($request))]);
    }

    public function remove(Request $request, string $userId, Friends $friends): Response
    {
        $friends->remove(PartiesController::userId($request), strtolower($userId));

        return new Response(status: 204);
    }
}
