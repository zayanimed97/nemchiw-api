<?php

namespace Modules\SocialAuth\Providers;

/** Who the provider vouches for. Names and email are untrusted profile hints. */
final class ProviderIdentity
{
    public function __construct(
        public readonly string $provider,
        public readonly string $subject,
        public readonly ?string $email = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
    ) {}

    /** @param  array<string, mixed>  $claims */
    public static function fromClaims(string $provider, array $claims): self
    {
        $verified = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? null) === 'true';

        return new self(
            $provider,
            (string) $claims['sub'],
            $verified && is_string($claims['email'] ?? null) ? $claims['email'] : null,
            is_string($claims['given_name'] ?? null) ? $claims['given_name'] : null,
            is_string($claims['family_name'] ?? null) ? $claims['family_name'] : null,
        );
    }
}
