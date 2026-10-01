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
    private const BACKOFF_SECONDS = 30;

    /** @return array<string, Key> keyed by kid, RS256 only */
    public function keys(string $url, string $kid): array
    {
        $key = $this->cacheKey($url);
        $jwks = Cache::get($key);
        if (! is_array($jwks)) {
            $jwks = $this->fetchOrBackOff($url);
            Cache::put($key, $jwks, (int) config('social_auth.jwks_ttl'));
        }
        $keys = JWK::parseKeySet($jwks, 'RS256');

        if (! isset($keys[$kid]) && Cache::add($key.':cooldown', true, (int) config('social_auth.jwks_refresh_cooldown'))) {
            try {
                $fresh = $this->fetchOrBackOff($url);
                Cache::put($key, $fresh, (int) config('social_auth.jwks_ttl'));
                $keys = JWK::parseKeySet($fresh, 'RS256');
            } catch (ApiException) {
                // Keep the keys we have: a failed refetch must not break valid tokens.
            }
        }

        return $keys;
    }

    /**
     * After a failure, answer "unavailable" for a short while without calling out, so a
     * provider outage does not turn into one slow outbound request per sign-in.
     *
     * @return array{keys: list<array<string, mixed>>}
     */
    private function fetchOrBackOff(string $url): array
    {
        $down = $this->cacheKey($url).':down';
        if (Cache::has($down)) {
            throw new ApiException(ApiErrorCode::ProviderUnavailable, 'Sign-in provider unavailable');
        }

        try {
            return $this->fetch($url);
        } catch (ApiException $e) {
            Cache::put($down, true, self::BACKOFF_SECONDS);

            throw $e;
        }
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
