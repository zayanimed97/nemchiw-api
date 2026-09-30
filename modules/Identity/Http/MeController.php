<?php

namespace Modules\Identity\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Events\AccountDeleting;
use Modules\Identity\Models\User;

final class MeController
{
    public function show(Request $request): JsonResponse
    {
        return new JsonResponse(ProfileResource::make($this->user($request))->resolve());
    }

    public function signOut(Request $request): Response
    {
        $this->user($request)->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $user = $this->user($request);

        DB::transaction(function () use ($user) {
            event(new AccountDeleting($user->id, $user->phone));
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
