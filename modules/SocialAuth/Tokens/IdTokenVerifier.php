<?php

namespace Modules\SocialAuth\Tokens;

use Firebase\JWT\JWT;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Throwable;

/**
 * Verifies an OpenID Connect ID token: RS256 signature from the provider's published
 * keys, issuer, audience, expiry and subject. Anything off is `unauthenticated`.
 */
final class IdTokenVerifier
{
    public function __construct(private readonly JwksKeys $jwks) {}

    /**
     * @param  list<string>  $issuers
     * @param  list<string>  $audiences  our client ids; empty means the provider is not configured
     * @return array<string, mixed>
     */
    public function verify(string $jwt, string $jwksUrl, array $issuers, array $audiences): array
    {
        if ($audiences === []) {
            throw new ApiException(ApiErrorCode::ProviderUnavailable, 'Sign-in provider not configured');
        }

        $kid = $this->kid($jwt);
        $keys = $this->jwks->keys($jwksUrl, $kid);

        // Pinned clock and leeway: firebase/php-jwt reads these statics.
        JWT::$timestamp = now()->getTimestamp();
        JWT::$leeway = (int) config('social_auth.leeway');
        try {
            $claims = json_decode(json_encode(JWT::decode($jwt, $keys)), true);
        } catch (Throwable) {
            throw self::invalid();
        } finally {
            JWT::$timestamp = null;
        }

        $audience = (array) ($claims['aud'] ?? []);
        if (! in_array($claims['iss'] ?? null, $issuers, true)
            || array_intersect($audience, $audiences) === []
            || ! is_numeric($claims['exp'] ?? null)
            || ! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw self::invalid();
        }

        return $claims;
    }

    public static function invalid(): ApiException
    {
        return new ApiException(ApiErrorCode::Unauthenticated, 'Invalid sign-in token');
    }

    private function kid(string $jwt): string
    {
        $header = json_decode((string) base64_decode(strtr(explode('.', $jwt)[0], '-_', '+/'), true), true);
        $kid = is_array($header) ? ($header['kid'] ?? null) : null;

        if (! is_string($kid) || $kid === '' || ($header['alg'] ?? null) !== 'RS256') {
            throw self::invalid();
        }

        return $kid;
    }
}
