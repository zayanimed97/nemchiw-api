<?php

use Illuminate\Support\Facades\Http;
use Modules\Identity\Models\User;
use Modules\SocialAuth\Actions\SignInWithProvider;
use Modules\SocialAuth\Models\SocialIdentity;
use Modules\SocialAuth\Providers\ProviderIdentity;
use Modules\SocialAuth\Testing\TokenFactory;

beforeEach(function () {
    config([
        'social_auth.google.client_ids' => ['web-client'],
        'social_auth.apple.client_ids' => ['tn.nemchiw.app'],
    ]);
    Http::fake([
        'www.googleapis.com/*' => Http::response(TokenFactory::jwks()),
        'appleid.apple.com/*' => Http::response(TokenFactory::jwks()),
    ]);
});

function googleToken(string $sub = 'g-1', array $extra = []): string
{
    return TokenFactory::sign(array_merge([
        'iss' => 'https://accounts.google.com', 'aud' => 'web-client', 'sub' => $sub,
        'iat' => now()->getTimestamp(), 'exp' => now()->addHour()->getTimestamp(),
    ], $extra));
}

function appleToken(string $sub = 'a-1', string $nonce = 'n-1', array $extra = []): string
{
    return TokenFactory::sign(array_merge([
        'iss' => 'https://appleid.apple.com', 'aud' => 'tn.nemchiw.app', 'sub' => $sub, 'nonce' => hash('sha256', $nonce),
        'iat' => now()->getTimestamp(), 'exp' => now()->addHour()->getTimestamp(),
    ], $extra));
}

function socialSignIn(array $body)
{
    return test()->postJson('/api/v1/auth/social', $body);
}

it('creates an account on the first Google sign-in, then finds it again', function () {
    $first = socialSignIn(['provider' => 'google', 'token' => googleToken(extra: ['given_name' => 'Sami'])])
        ->assertOk()
        ->assertJson(['isNew' => true, 'profile' => ['firstName' => 'Sami', 'providers' => ['google']]])
        ->assertJsonStructure(['token']);

    socialSignIn(['provider' => 'google', 'token' => googleToken()])
        ->assertOk()
        ->assertJson(['isNew' => false, 'profile' => ['id' => $first->json('profile.id')]]);

    expect(User::count())->toBe(1);
});

it('puts Apple first-time names on the new account and never overwrites later', function () {
    socialSignIn([
        'provider' => 'apple', 'token' => appleToken(), 'nonce' => 'n-1',
        'hint' => ['firstName' => 'Amel', 'lastName' => 'Gharbi', 'email' => 'amel@example.tn'],
    ])->assertOk()->assertJson(['isNew' => true, 'profile' => ['firstName' => 'Amel', 'lastName' => 'Gharbi', 'email' => 'amel@example.tn']]);

    socialSignIn([
        'provider' => 'apple', 'token' => appleToken(nonce: 'n-2'), 'nonce' => 'n-2',
        'hint' => ['firstName' => 'Someone Else'],
    ])->assertOk()->assertJson(['isNew' => false, 'profile' => ['firstName' => 'Amel']]);
});

it('cleans the hint before using it', function () {
    socialSignIn([
        'provider' => 'google', 'token' => googleToken(),
        'hint' => ['firstName' => "  \u{202E}Sa\u{200B}mi".str_repeat('x', 60), 'lastName' => "\u{0007}", 'email' => 'not an email'],
    ])->assertOk();

    $user = User::first();
    expect($user->first_name)->toBe('Sami'.str_repeat('x', 36));
    expect($user->last_name)->toBeNull();
    expect($user->email)->toBeNull();
});

it('prefers the provider email over the hint', function () {
    socialSignIn([
        'provider' => 'google', 'token' => googleToken(extra: ['email' => 'real@gmail.com', 'email_verified' => true]),
        'hint' => ['email' => 'typed@example.tn'],
    ])->assertOk()->assertJsonPath('profile.email', 'real@gmail.com');
});

it('never links accounts by email', function () {
    socialSignIn(['provider' => 'google', 'token' => googleToken(extra: ['email' => 'same@x.tn', 'email_verified' => true])])->assertOk();
    socialSignIn(['provider' => 'apple', 'token' => appleToken(extra: ['email' => 'same@x.tn', 'email_verified' => 'true']), 'nonce' => 'n-1'])
        ->assertOk()->assertJsonPath('isNew', true);

    expect(User::count())->toBe(2);
});

it('ends a race for the same identity in one account', function () {
    // The losing side of a race: our lookup found nothing, but by the time we insert,
    // the other request has linked the identity to its own new account.
    $winner = User::factory()->create();
    (new SocialIdentity)->forceFill(['user_id' => $winner->id, 'provider' => 'google', 'provider_user_id' => 'g-1'])->save();

    $result = app(SignInWithProvider::class)->firstSignIn(new ProviderIdentity('google', 'g-1'), ['firstName' => 'Loser']);

    expect($result['isNew'])->toBeFalse();
    expect($result['profile']['id'])->toBe($winner->id);
    expect(User::count())->toBe(1);
    expect(SocialIdentity::count())->toBe(1);
});

it('lists linked providers on the profile', function () {
    $token = socialSignIn(['provider' => 'google', 'token' => googleToken()])->json('token');
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/me')->assertJsonPath('providers', ['google']);
});

it('forgets identities when the account is deleted', function () {
    $token = socialSignIn(['provider' => 'google', 'token' => googleToken()])->json('token');
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->deleteJson('/api/v1/me')->assertNoContent();
    $this->app['auth']->forgetGuards();

    expect(SocialIdentity::count())->toBe(0);
    socialSignIn(['provider' => 'google', 'token' => googleToken()])->assertOk()->assertJsonPath('isNew', true);
});

it('refuses a bad token', function () {
    socialSignIn(['provider' => 'google', 'token' => googleToken(extra: ['aud' => 'someone-else'])])
        ->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
});

it('validates the request', function (array $body) {
    socialSignIn($body)->assertStatus(422)->assertJsonPath('code', 'validation');
})->with([
    'unknown provider' => [['provider' => 'myspace', 'token' => 'x']],
    'no token' => [['provider' => 'google']],
    'hint with extra keys' => [['provider' => 'google', 'token' => 'x', 'hint' => ['phone' => '+21620123456']]],
]);

it('throttles sign-in attempts per IP', function () {
    config(['social_auth.per_ip_per_10_min' => 2]);
    socialSignIn(['provider' => 'google', 'token' => 'bad'])->assertStatus(401);
    socialSignIn(['provider' => 'google', 'token' => 'bad'])->assertStatus(401);
    socialSignIn(['provider' => 'google', 'token' => 'bad'])->assertStatus(429);
});
