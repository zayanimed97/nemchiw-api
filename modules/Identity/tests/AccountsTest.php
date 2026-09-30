<?php

use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\Accounts;
use Modules\Identity\Models\User;
use Modules\Shared\Errors\ApiException;

it('creates an account for a new phone', function () {
    $result = app(Accounts::class)->signInWithPhone('+21620123456');

    expect($result['isNew'])->toBeTrue();
    expect($result['token'])->toStartWith((string) User::first()->tokens()->first()->id.'|nemchiw_');
    expect($result['profile']['phone'])->toBe('+21620123456');
    expect(User::first()->phone_verified_at)->not->toBeNull();
});

it('signs an existing phone into the same account', function () {
    $first = app(Accounts::class)->signInWithPhone('+21620123456');
    $second = app(Accounts::class)->signInWithPhone('+21620123456');

    expect($second['isNew'])->toBeFalse();
    expect($second['profile']['id'])->toBe($first['profile']['id']);
    expect(User::count())->toBe(1);
});

it('attaches a phone, and refuses one owned by someone else', function () {
    $owner = User::factory()->withPhone('+21620123456')->create();
    $other = User::factory()->create();

    try {
        app(Accounts::class)->attachPhone($other->id, '+21620123456');
        $this->fail('expected phone_taken');
    } catch (ApiException $e) {
        expect($e->error->value)->toBe('phone_taken');
    }

    $profile = app(Accounts::class)->attachPhone($other->id, '+21655000111');
    expect($profile['phone'])->toBe('+21655000111');
    expect(app(Accounts::class)->ownerOfPhone('+21620123456'))->toBe($owner->id);
});

it('stores the emergency contact encrypted', function () {
    $user = User::factory()->create(['emergency_contact' => ['name' => 'Salma', 'phone' => '+21620000001']]);
    $raw = DB::table('users')->where('id', $user->id)->value('emergency_contact');
    expect($raw)->not->toContain('Salma');
    expect($user->fresh()->emergency_contact)->toBe(['name' => 'Salma', 'phone' => '+21620000001']);
});
