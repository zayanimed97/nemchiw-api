<?php

namespace Modules\SocialAuth\Tokens;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Throwable;

/**
 * A provider's signing keys, cached. An unknown key id (keys rotate) triggers one
 * refetch per provider per cooldown, so tokens with made-up key ids cannot make us
 * hammer the provider.
 */
final class JwksKeys
{
    /** @return array<string, Key> keyed by kid, RS256 only */
    public function keys(string $url, string $kid): array
    {
        $keys = $this->load($url, fresh: false);

        if (! isset($keys[$kid]) && Cache::add($this->cacheKey($url).':cooldown', true, (int) config('social_auth.jwks_refresh_cooldown'))) {
            $keys = $this->load($url, fresh: true);
        }

        return $keys;
    }

    /** @return array<string, Key> */
    private function load(string $url, bool $fresh): array
    {
        $key = $this->cacheKey($url);
        if ($fresh) {
            Cache::forget($key);
        }

        $jwks = Cache::remember($key, (int) config('social_auth.jwks_ttl'), fn () => $this->fetch($url));

        return JWK::parseKeySet($jwks, 'RS256');
    }

    /** @return array{keys: list<array<string, mixed>>} */
    private function fetch(string $url): array
    {
        try {
            $response = Http::acceptJson()->timeout(5)->connectTimeout(3)->get($url);
        } catch (ConnectionException) {
            throw new ApiException(ApiErrorCode::ProviderUnavailable, 'Sign-in provider unreachable');
        }

        $jwks = $response->successful() ? $response->json() : null;
        if (! is_array($jwks) || ! is_array($jwks['keys'] ?? null) || $jwks['keys'] === []) {
            throw new ApiException(ApiErrorCode::ProviderUnavailable, 'Sign-in provider keys unavailable');
        }

        try {
            JWK::parseKeySet($jwks, 'RS256');
        } catch (Throwable) {
            throw new ApiException(ApiErrorCode::ProviderUnavailable, 'Sign-in provider keys unreadable');
        }

        return $jwks;
    }

    private function cacheKey(string $url): string
    {
        return 'jwks:'.hash('sha256', $url);
    }
}
