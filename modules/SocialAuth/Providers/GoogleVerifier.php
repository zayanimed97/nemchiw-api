<?php

namespace Modules\SocialAuth\Providers;

use Modules\SocialAuth\Tokens\IdTokenVerifier;

final class GoogleVerifier implements ProviderVerifier
{
    public function __construct(private readonly IdTokenVerifier $tokens) {}

    public function verify(string $token, ?string $nonce): ProviderIdentity
    {
        $claims = $this->tokens->verify(
            $token,
            'https://www.googleapis.com/oauth2/v3/certs',
            ['https://accounts.google.com', 'accounts.google.com'],
            (array) config('social_auth.google.client_ids'),
        );

        return ProviderIdentity::fromClaims('google', $claims);
    }
}
