<?php

use Illuminate\Support\Facades\Event;
use Modules\Identity\Contracts\Accounts;
use Modules\Identity\Models\User;
use Modules\Shared\Events\ContactConfirmed;
use Modules\Shared\Support\ContactHash;

it('confirms a provider email once across accounts', function () {
    [$a, $b] = User::factory()->count(2)->create();
    app(Accounts::class)->confirmEmail($a->id, 'Anas@Gmail.com');
    app(Accounts::class)->confirmEmail($b->id, 'anas@gmail.com');

    expect($a->fresh()->confirmed_email)->toBe('anas@gmail.com')
        ->and($b->fresh()->confirmed_email)->toBeNull();
});

it('fills an empty email but keeps a typed one', function () {
    $empty = User::factory()->create();
    $typed = User::factory()->create(['email' => 'typed@x.tn']);
    app(Accounts::class)->confirmEmail($empty->id, 'A@x.tn');
    app(Accounts::class)->confirmEmail($typed->id, 'b@x.tn');

    expect($empty->fresh())->email->toBe('a@x.tn')->confirmed_email->toBe('a@x.tn')
        ->and($typed->fresh())->email->toBe('typed@x.tn')->confirmed_email->toBe('b@x.tn');
});

it('raises ContactConfirmed for a confirmed email, and not for a refused one', function () {
    Event::fake([ContactConfirmed::class]);
    [$a, $b] = User::factory()->count(2)->create();
    app(Accounts::class)->confirmEmail($a->id, 'Anas@Gmail.com');
    app(Accounts::class)->confirmEmail($b->id, 'anas@gmail.com');

    Event::assertDispatchedTimes(ContactConfirmed::class, 1);
    Event::assertDispatched(ContactConfirmed::class, fn ($e) => $e->userId === $a->id
        && $e->contactHash === ContactHash::of('anas@gmail.com'));
});

it('raises ContactConfirmed when a phone is attached', function () {
    Event::fake([ContactConfirmed::class]);
    $user = User::factory()->create();
    app(Accounts::class)->attachPhone($user->id, '+21698765432');

    Event::assertDispatched(ContactConfirmed::class, fn ($e) => $e->userId === $user->id
        && $e->contactHash === ContactHash::of('+21698765432'));
});

it('raises ContactConfirmed for a new phone account, and not for a returning sign-in', function () {
    Event::fake([ContactConfirmed::class]);
    $first = app(Accounts::class)->signInWithPhone('+21698765432');
    app(Accounts::class)->signInWithPhone('+21698765432');

    Event::assertDispatchedTimes(ContactConfirmed::class, 1);
    Event::assertDispatched(ContactConfirmed::class, fn ($e) => $e->userId === $first['profile']['id']);
});

it('clears confirmation when the user edits the email', function () {
    $user = User::factory()->create(['email' => 'a@x.tn', 'confirmed_email' => 'a@x.tn']);
    $token = tokenFor($user);

    $this->withToken($token)->postJson('/api/v1/me', ['firstName' => 'Anas'])->assertOk();
    expect($user->fresh()->confirmed_email)->toBe('a@x.tn');

    $this->withToken($token)->postJson('/api/v1/me', ['email' => 'A@x.tn'])->assertOk();
    expect($user->fresh()->confirmed_email)->toBe('a@x.tn');

    $this->withToken($token)->postJson('/api/v1/me', ['email' => 'other@x.tn'])->assertOk();
    expect($user->fresh()->confirmed_email)->toBeNull();
});

it('throws a clear error when the contact key is missing', function () {
    config(['parties.contact_key' => '']);
    ContactHash::of('+21698765432');
})->throws(RuntimeException::class, 'contact_key');
