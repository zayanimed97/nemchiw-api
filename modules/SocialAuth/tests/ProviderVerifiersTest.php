<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Shared\Errors\ApiException;
use Modules\SocialAuth\Providers\AppleVerifier;
use Modules\SocialAuth\Providers\FacebookVerifier;
use Modules\SocialAuth\Providers\GoogleVerifier;
use Modules\SocialAuth\Testing\TokenFactory;

const GOOGLE_KEYS = 'https://www.googleapis.com/oauth2/v3/certs';
const APPLE_KEYS = 'https://appleid.apple.com/auth/keys';
const FACEBOOK_KEYS = 'https://www.facebook.com/.well-known/oauth/openid/jwks/';

beforeEach(function () {
    config([
        'social_auth.google.client_ids' => ['web-client.apps.googleusercontent.com'],
        'social_auth.apple.client_ids' => ['tn.nemchiw.app'],
        'social_auth.facebook.app_id' => '111',
        'social_auth.facebook.app_secret' => 'fb-secret',
    ]);
});

function idToken(string $iss, string $aud, array $extra = []): string
{
    return TokenFactory::sign(array_merge([
        'iss' => $iss, 'aud' => $aud, 'sub' => 'subject-1',
        'iat' => now()->getTimestamp(), 'exp' => now()->addHour()->getTimestamp(),
    ], $extra));
}

function failsWith(callable $call, string $code): void
{
    try {
        $call();
        test()->fail("expected {$code}");
    } catch (ApiException $e) {
        expect($e->error->value)->toBe($code);
    }
}

it('verifies a Google ID token', function () {
    Http::fake([GOOGLE_KEYS => Http::response(TokenFactory::jwks())]);
    $token = idToken('https://accounts.google.com', 'web-client.apps.googleusercontent.com', [
        'email' => 'sami@gmail.com', 'email_verified' => true, 'given_name' => 'Sami', 'family_name' => 'Ben Salah',
    ]);

    $identity = app(GoogleVerifier::class)->verify($token, null);

    expect($identity->provider)->toBe('google');
    expect($identity->subject)->toBe('subject-1');
    expect($identity->email)->toBe('sami@gmail.com');
    expect([$identity->firstName, $identity->lastName])->toBe(['Sami', 'Ben Salah']);
});

it('accepts the short Google issuer and drops unverified emails', function () {
    Http::fake([GOOGLE_KEYS => Http::response(TokenFactory::jwks())]);
    $token = idToken('accounts.google.com', 'web-client.apps.googleusercontent.com', ['email' => 'x@y.tn', 'email_verified' => false]);

    expect(app(GoogleVerifier::class)->verify($token, null)->email)->toBeNull();
});

it('refuses a Google token for another app', function () {
    Http::fake([GOOGLE_KEYS => Http::response(TokenFactory::jwks())]);
    failsWith(fn () => app(GoogleVerifier::class)->verify(idToken('https://accounts.google.com', 'other.apps.googleusercontent.com'), null), 'unauthenticated');
});

it('verifies an Apple token against the hashed nonce', function () {
    Http::fake([APPLE_KEYS => Http::response(TokenFactory::jwks())]);
    $token = idToken('https://appleid.apple.com', 'tn.nemchiw.app', [
        'nonce' => hash('sha256', 'raw-nonce'), 'email' => 'relay@privaterelay.appleid.com', 'email_verified' => 'true',
    ]);

    $identity = app(AppleVerifier::class)->verify($token, 'raw-nonce');

    expect([$identity->provider, $identity->subject, $identity->email])->toBe(['apple', 'subject-1', 'relay@privaterelay.appleid.com']);
});

it('refuses an Apple token without the right nonce', function (?string $nonce) {
    Http::fake([APPLE_KEYS => Http::response(TokenFactory::jwks())]);
    $token = idToken('https://appleid.apple.com', 'tn.nemchiw.app', ['nonce' => hash('sha256', 'raw-nonce')]);

    failsWith(fn () => app(AppleVerifier::class)->verify($token, $nonce), 'unauthenticated');
})->with(['missing' => [null], 'wrong' => ['other-nonce'], 'hash sent as raw' => [hash('sha256', 'raw-nonce')]]);

it('verifies a Facebook Limited Login token against the raw nonce', function () {
    Http::fake([FACEBOOK_KEYS => Http::response(TokenFactory::jwks())]);
    $token = idToken('https://www.facebook.com', '111', ['nonce' => 'raw-nonce', 'given_name' => 'Amel', 'family_name' => 'Gharbi']);

    $identity = app(FacebookVerifier::class)->verify($token, 'raw-nonce');

    expect([$identity->provider, $identity->subject, $identity->firstName])->toBe(['facebook', 'subject-1', 'Amel']);
});

it('refuses a Facebook Limited Login token without the right nonce', function (?string $nonce) {
    Http::fake([FACEBOOK_KEYS => Http::response(TokenFactory::jwks())]);
    $token = idToken('https://www.facebook.com', '111', ['nonce' => 'raw-nonce']);

    failsWith(fn () => app(FacebookVerifier::class)->verify($token, $nonce), 'unauthenticated');
})->with(['missing' => [null], 'wrong' => ['other']]);

it('checks a Facebook access token with Graph', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => ['is_valid' => true, 'app_id' => '111', 'user_id' => '987']])]);

    $identity = app(FacebookVerifier::class)->verify('EAAB-access-token', null);

    expect([$identity->provider, $identity->subject])->toBe(['facebook', '987']);
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://graph.facebook.com/v23.0/debug_token?')
        && $request['input_token'] === 'EAAB-access-token'
        && $request['access_token'] === '111|fb-secret');
});

it('refuses a Facebook access token that is invalid or belongs to another app', function (array $data) {
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => $data])]);
    failsWith(fn () => app(FacebookVerifier::class)->verify('EAAB-access-token', null), 'unauthenticated');
})->with([
    'invalid' => [['is_valid' => false, 'app_id' => '111', 'user_id' => '987']],
    'other app' => [['is_valid' => true, 'app_id' => '222', 'user_id' => '987']],
    'no user' => [['is_valid' => true, 'app_id' => '111']],
]);

it('is unavailable when Graph cannot be reached or Facebook is not configured', function () {
    Http::fake(['graph.facebook.com/*' => fn () => throw new ConnectionException('timeout for https://graph.facebook.com/?access_token=111|fb-secret')]);
    failsWith(fn () => app(FacebookVerifier::class)->verify('EAAB-access-token', null), 'provider_unavailable');

    config(['social_auth.facebook.app_secret' => '']);
    failsWith(fn () => app(FacebookVerifier::class)->verify('EAAB-access-token', null), 'provider_unavailable');
});
