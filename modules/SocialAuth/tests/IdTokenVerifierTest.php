<?php

use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Shared\Errors\ApiException;
use Modules\SocialAuth\Testing\TokenFactory;
use Modules\SocialAuth\Tokens\IdTokenVerifier;

const JWKS_URL = 'https://keys.example.test/jwks';

function claims(array $override = []): array
{
    return array_merge([
        'iss' => 'https://issuer.test', 'aud' => 'our-app', 'sub' => 'user-1',
        'iat' => now()->getTimestamp(), 'exp' => now()->addHour()->getTimestamp(),
    ], $override);
}

function verifyToken(string $jwt, array $audiences = ['our-app']): array
{
    return app(IdTokenVerifier::class)->verify($jwt, JWKS_URL, ['https://issuer.test'], $audiences);
}

function expectApiError(callable $call, string $code): void
{
    try {
        $call();
        test()->fail("expected {$code}");
    } catch (ApiException $e) {
        expect($e->error->value)->toBe($code);
    }
}

/** Serves the provider's key set; pass several to answer successive fetches in order. */
function fakeKeys(array ...$sets): void
{
    $sequence = Http::sequence();
    foreach ($sets ?: [TokenFactory::jwks()] as $set) {
        $sequence->push($set);
    }
    Http::fake([JWKS_URL => $sequence->whenEmpty(Http::response(end($sets) ?: TokenFactory::jwks()))]);
}

it('accepts a valid token and returns its claims', function () {
    fakeKeys();
    expect(verifyToken(TokenFactory::sign(claims()))['sub'])->toBe('user-1');
});

it('accepts an audience list that contains ours', function () {
    fakeKeys();
    expect(verifyToken(TokenFactory::sign(claims(['aud' => ['other', 'our-app']])))['sub'])->toBe('user-1');
});

it('rejects tokens that are not exactly ours', function (array $override) {
    fakeKeys();
    expectApiError(fn () => verifyToken(TokenFactory::sign(claims($override))), 'unauthenticated');
})->with([
    'another app' => [['aud' => 'their-app']],
    'another issuer' => [['iss' => 'https://evil.test']],
    'expired' => [['exp' => now()->subMinutes(5)->getTimestamp()]],
    'no expiry' => [['exp' => null]],
    'no subject' => [['sub' => '']],
    'issued in the future' => [['iat' => now()->addHour()->getTimestamp()]],
]);

it('rejects a token signed by a key the provider does not publish', function () {
    fakeKeys();
    expectApiError(fn () => verifyToken(TokenFactory::sign(claims(), 'stranger')), 'unauthenticated');
});

it('rejects alg none', function () {
    fakeKeys();
    $header = rtrim(strtr(base64_encode('{"alg":"none","kid":"test-key"}'), '+/', '-_'), '=');
    $body = rtrim(strtr(base64_encode(json_encode(claims())), '+/', '-_'), '=');
    expectApiError(fn () => verifyToken("{$header}.{$body}."), 'unauthenticated');
});

it('rejects HS256 signed with the public key (key confusion)', function () {
    fakeKeys();
    $forged = JWT::encode(claims(), TokenFactory::publicPem(), 'HS256', 'test-key');
    expectApiError(fn () => verifyToken($forged), 'unauthenticated');
});

it('rejects garbage', function (string $garbage) {
    fakeKeys();
    expectApiError(fn () => verifyToken($garbage), 'unauthenticated');
})->with(['not-a-jwt', 'a.b.c', '', '...']);

it('is unavailable when no audience is configured', function () {
    fakeKeys();
    expectApiError(fn () => verifyToken(TokenFactory::sign(claims()), []), 'provider_unavailable');
});

it('fetches the provider keys once and caches them', function () {
    fakeKeys();
    verifyToken(TokenFactory::sign(claims()));
    verifyToken(TokenFactory::sign(claims(['sub' => 'user-2'])));
    Http::assertSentCount(1);
});

it('refetches once for an unknown key id, then waits out the cooldown', function () {
    fakeKeys();
    verifyToken(TokenFactory::sign(claims()));
    expectApiError(fn () => verifyToken(TokenFactory::sign(claims(), 'rotated-1')), 'unauthenticated');
    expectApiError(fn () => verifyToken(TokenFactory::sign(claims(), 'rotated-2')), 'unauthenticated');
    Http::assertSentCount(2);

    $this->travel(61)->seconds();
    expectApiError(fn () => verifyToken(TokenFactory::sign(claims(), 'rotated-3')), 'unauthenticated');
    Http::assertSentCount(3);
});

it('picks up a rotated key after a refetch', function () {
    fakeKeys(TokenFactory::jwks(), ['keys' => array_merge(TokenFactory::jwks()['keys'], TokenFactory::jwks('new-key')['keys'])]);
    verifyToken(TokenFactory::sign(claims()));

    expect(verifyToken(TokenFactory::sign(claims(), 'new-key'))['sub'])->toBe('user-1');
});

it('is unavailable when the provider keys cannot be fetched', function (Closure $response) {
    Http::fake([JWKS_URL => $response]);
    expectApiError(fn () => verifyToken(TokenFactory::sign(claims())), 'provider_unavailable');
})->with([
    'down' => [fn () => fn () => throw new ConnectionException('timeout')],
    'error' => [fn () => fn () => Http::response('oops', 500)],
    'not a key set' => [fn () => fn () => Http::response(['nope' => true])],
]);
