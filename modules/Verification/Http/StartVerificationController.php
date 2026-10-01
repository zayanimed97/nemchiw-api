<?php

namespace Modules\Verification\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Verification\Actions\StartVerification;

final class StartVerificationController
{
    private const LANGUAGES = ['ar', 'fr', 'en'];

    public function __invoke(Request $request, StartVerification $start): JsonResponse
    {
        $language = substr((string) $request->header('Accept-Language', ''), 0, 2);

        return new JsonResponse($start(
            (string) $request->user()->getAuthIdentifier(),
            in_array($language, self::LANGUAGES, true) ? $language : 'fr',
        ));
    }
}
