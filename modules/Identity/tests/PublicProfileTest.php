<?php

use Illuminate\Http\Request;
use Modules\Identity\Contracts\People;
use Modules\Identity\Http\PublicProfileResource;
use Modules\Identity\Models\User;

it('shows others only the public fields', function () {
    $this->travelTo('2026-09-30');
    $user = User::factory()->withPhone('+21620123456')->create([
        'first_name' => 'Salma', 'last_name' => 'Secretname', 'gender' => 'female',
        'birth_date' => '1995-10-01', 'email' => 'salma@example.tn',
        'emergency_contact' => ['name' => 'Mounir', 'phone' => '+21698000111'],
        'skills' => ['knots'], 'car_seats' => 3,
    ]);

    $public = PublicProfileResource::make($user)->resolve(Request::create('/'));

    expect(array_keys($public))->toEqualCanonicalizing([
        'id', 'firstName', 'level', 'skills', 'bio', 'homeArea', 'car', 'joinedAt', 'campsCount', 'age', 'photo', 'gender',
    ]);
    expect($public['age'])->toBe(30);
    $json = json_encode($public);
    foreach (['20123456', 'Secretname', '1995', 'salma@', 'Mounir', '98000111'] as $secret) {
        expect($json)->not->toContain($secret);
    }
});

it('shows gender on the public card', function () {
    $u = User::factory()->create(['gender' => 'female']);
    expect(app(People::class)->publicProfiles([$u->id])[$u->id]['gender'])->toBe('female');
});
