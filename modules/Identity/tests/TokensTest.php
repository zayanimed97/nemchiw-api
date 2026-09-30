<?php

use Modules\Identity\Contracts\Accounts;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Models\User;

it('expires a token unused for 90 days', function () {
    $token = tokenFor(User::factory()->create());
    $this->travel(91)->days();
    $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
});

it('slides the expiry when the token is used', function () {
    $token = tokenFor(User::factory()->create());

    $this->travel(60)->days();
    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    $this->app['auth']->forgetGuards();

    $this->travel(60)->days();
    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
});

it('writes last_used_at at most every 15 minutes', function () {
    $token = tokenFor(User::factory()->create());
    $this->freezeTime();

    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    $first = PersonalAccessToken::first()->last_used_at;
    $this->app['auth']->forgetGuards();

    $this->travel(5)->minutes();
    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    expect(PersonalAccessToken::first()->last_used_at)->toEqual($first);
    $this->app['auth']->forgetGuards();

    $this->travel(11)->minutes();
    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    expect(PersonalAccessToken::first()->last_used_at)->not->toEqual($first);
});

it('keeps at most 10 tokens per user, dropping the oldest', function () {
    $first = app(Accounts::class)->signInWithPhone('+21620123456')['token'];
    foreach (range(1, 10) as $_) {
        $this->travel(1)->seconds();
        app(Accounts::class)->signInWithPhone('+21620123456');
    }

    expect(PersonalAccessToken::count())->toBe(10);
    $this->withToken($first)->getJson('/api/v1/me')->assertStatus(401);
});
