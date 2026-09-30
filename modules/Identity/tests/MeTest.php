<?php

use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Identity\Events\AccountDeleting;
use Modules\Identity\Models\User;

it('requires a token', function () {
    $this->getJson('/api/v1/me')->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
});

it('returns the owner profile in the contract shape', function () {
    $user = User::factory()->withPhone('+21620123456')->create(['first_name' => 'Sami']);

    $response = $this->withToken(tokenFor($user))->getJson('/api/v1/me')->assertOk();

    expect(array_keys($response->json()))->toEqualCanonicalizing([
        'id', 'firstName', 'lastName', 'gender', 'birthDate', 'phone', 'email', 'photo', 'level',
        'skills', 'bio', 'homeArea', 'car', 'emergencyContact', 'providers', 'joinedAt', 'campsCount',
    ]);
    expect($response->json())->toMatchArray([
        'id' => $user->id, 'firstName' => 'Sami', 'phone' => '+21620123456',
        'skills' => [], 'providers' => [], 'photo' => null, 'campsCount' => 0,
    ]);
});

it('signs out by revoking only the current token', function () {
    $user = User::factory()->create();
    $kept = tokenFor($user);
    $revoked = tokenFor($user);

    $this->withToken($revoked)->postJson('/api/v1/auth/sign-out')->assertNoContent();
    $this->app['auth']->forgetGuards();

    $this->withToken($revoked)->getJson('/api/v1/me')->assertStatus(401);
    $this->app['auth']->forgetGuards();
    $this->withToken($kept)->getJson('/api/v1/me')->assertOk();
});

it('deletes the account, its tokens, and tells other modules first', function () {
    Event::fake([AccountDeleting::class]);
    $user = User::factory()->withPhone('+21620123456')->create();
    $token = tokenFor($user);

    $this->withToken($token)->deleteJson('/api/v1/me')->assertNoContent();

    expect(User::count())->toBe(0);
    expect(PersonalAccessToken::count())->toBe(0);
    Event::assertDispatched(AccountDeleting::class, fn ($e) => $e->userId === $user->id && $e->phone === '+21620123456');
});
