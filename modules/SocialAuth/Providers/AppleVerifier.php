<?php

namespace Modules\SocialAuth\Providers;

use Modules\SocialAuth\Tokens\IdTokenVerifier;

final class AppleVerifier implements ProviderVerifier
{
    public function __construct(private readonly IdTokenVerifier $tokens) {}

    /** The app hands Apple sha256(nonce) and sends us the raw nonce: a stolen token alone is useless. */
    public function verify(string $token, ?string $nonce): ProviderIdentity
    {
        $claims = $this->tokens->verify(
            $token,
            'https://appleid.apple.com/auth/keys',
            ['https://appleid.apple.com'],
            (array) config('social_auth.apple.client_ids'),
        );

        if ($nonce === null || ! hash_equals((string) ($claims['nonce'] ?? ''), hash('sha256', $nonce))) {
            throw IdTokenVerifier::invalid();
        }

        return ProviderIdentity::fromClaims('apple', $claims);
    }
}
