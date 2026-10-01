<?php

namespace Modules\SocialAuth\Providers;

use Modules\Shared\Errors\ApiException;

interface ProviderVerifier
{
    /** @throws ApiException unauthenticated | provider_unavailable */
    public function verify(string $token, ?string $nonce): ProviderIdentity;
}
