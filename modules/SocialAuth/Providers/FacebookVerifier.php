<?php

namespace Modules\SocialAuth\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\SocialAuth\Tokens\IdTokenVerifier;

/**
 * iOS sends a Limited Login ID token (a JWT carrying our raw nonce); Android sends a
 * classic access token, which only Graph can vouch for.
 */
final class FacebookVerifier implements ProviderVerifier
{
    public function __construct(private readonly IdTokenVerifier $tokens) {}

    public function verify(string $token, ?string $nonce): ProviderIdentity
    {
        $appId = (string) config('social_auth.facebook.app_id');

        return substr_count($token, '.') === 2
            ? $this->limitedLogin($token, $nonce, $appId)
            : $this->accessToken($token, $appId);
    }

    private function limitedLogin(string $token, ?string $nonce, string $appId): ProviderIdentity
    {
        $claims = $this->tokens->verify(
            $token,
            'https://www.facebook.com/.well-known/oauth/openid/jwks/',
            ['https://www.facebook.com'],
            $appId === '' ? [] : [$appId],
        );

        if ($nonce === null || ! hash_equals((string) ($claims['nonce'] ?? ''), $nonce)) {
            throw IdTokenVerifier::invalid();
        }

        // Facebook only shares emails it has confirmed.
        return ProviderIdentity::fromClaims('facebook', $claims + ['email_verified' => true]);
    }

    private function accessToken(string $token, string $appId): ProviderIdentity
    {
        // Facebook access tokens are long alphanumeric strings; anything else is refused
        // here rather than costing a Graph call (and our app's Graph rate limit).
        if (preg_match('/^[A-Za-z0-9]{20,512}$/', $token) !== 1) {
            throw IdTokenVerifier::invalid();
        }

        $secret = (string) config('social_auth.facebook.app_secret');
        if ($appId === '' || $secret === '') {
            throw new ApiException(ApiErrorCode::ProviderUnavailable, 'Sign-in provider not configured');
        }

        try {
            $response = Http::acceptJson()->timeout(5)->connectTimeout(3)->get(
                sprintf('https://graph.facebook.com/%s/debug_token', rawurlencode((string) config('social_auth.facebook.graph_version'))),
                ['input_token' => $token, 'access_token' => "{$appId}|{$secret}"],
            );
        } catch (ConnectionException) {
            // The exception text holds the URL, which holds the app secret: never pass it on.
            throw new ApiException(ApiErrorCode::ProviderUnavailable, 'Sign-in provider unreachable');
        }

        // A top-level error is about *our* app token (wrong secret, rate limit), not the
        // person's token: that is our problem to fix, so say unavailable and log it.
        if ($response->serverError() || $response->json('error') !== null) {
            Log::warning('Facebook sign-in: Graph refused our app', [
                'status' => $response->status(),
                'meta_code' => $response->json('error.code'),
            ]);

            throw new ApiException(ApiErrorCode::ProviderUnavailable, 'Sign-in provider unavailable');
        }

        $data = (array) $response->json('data');
        $userId = $data['user_id'] ?? null;
        if (($data['is_valid'] ?? false) !== true || (string) ($data['app_id'] ?? '') !== $appId || ! is_string($userId) || $userId === '') {
            throw IdTokenVerifier::invalid();
        }

        return new ProviderIdentity('facebook', $userId);
    }
}
