<?php

namespace Modules\SocialAuth\Http;

use Illuminate\Http\JsonResponse;
use Modules\SocialAuth\Actions\SignInWithProvider;

final class SocialSignInController
{
    public function __invoke(SocialSignInRequest $request, SignInWithProvider $signIn): JsonResponse
    {
        $data = $request->validated();

        return new JsonResponse($signIn($data['provider'], $data['token'], $data['nonce'] ?? null, (array) ($data['hint'] ?? [])));
    }
}
