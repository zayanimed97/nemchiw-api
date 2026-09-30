<?php

use Illuminate\Http\Request;
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
        'id', 'firstName', 'level', 'skills', 'bio', 'homeArea', 'car', 'joinedAt', 'campsCount', 'age', 'photo',
    ]);
    expect($public['age'])->toBe(30);
    $json = json_encode($public);
    foreach (['20123456', 'Secretname', 'female', '1995', 'salma@', 'Mounir', '98000111'] as $secret) {
        expect($json)->not->toContain($secret);
    }
});
