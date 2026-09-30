<?php

use Modules\Identity\Models\User;

beforeEach(function () {
    $this->user = User::factory()->withPhone('+21620123456')->create(['first_name' => 'Sami']);
    $this->withToken(tokenFor($this->user));
});

it('updates every editable field', function () {
    $this->patchJson('/api/v1/me', [
        'firstName' => 'Salma',
        'lastName' => 'Ben Ali',
        'gender' => 'female',
        'birthDate' => '1995-04-12',
        'email' => 'salma@example.tn',
        'level' => 'intermediate',
        'skills' => ['firstAid', 'knots'],
        'bio' => 'Bivouac tous les mois.',
        'homeArea' => ['governorate' => 'nabeul', 'city' => 'Kelibia'],
        'car' => ['seats' => 4],
        'emergencyContact' => ['name' => 'Mounir', 'phone' => '+21698000111'],
    ])->assertOk()->assertJson([
        'firstName' => 'Salma',
        'lastName' => 'Ben Ali',
        'birthDate' => '1995-04-12',
        'skills' => ['firstAid', 'knots'],
        'homeArea' => ['governorate' => 'nabeul', 'city' => 'Kelibia'],
        'car' => ['seats' => 4],
        'emergencyContact' => ['name' => 'Mounir', 'phone' => '+21698000111'],
    ]);
});

it('changes only the fields sent, and null clears optional ones', function () {
    $this->patchJson('/api/v1/me', ['lastName' => 'Trabelsi', 'car' => ['seats' => 2]])->assertOk();
    $this->patchJson('/api/v1/me', ['car' => null])->assertOk()
        ->assertJson(['firstName' => 'Sami', 'lastName' => 'Trabelsi', 'car' => null]);
});

it('ignores fields that are not editable', function () {
    $this->patchJson('/api/v1/me', ['phone' => '+21655555555', 'id' => 'x', 'campsCount' => 99, 'firstName' => 'Sami'])
        ->assertOk()
        ->assertJson(['phone' => '+21620123456', 'id' => $this->user->id, 'campsCount' => 0]);
});

it('rejects invalid values with the field name', function (array $patch, string $field) {
    $this->patchJson('/api/v1/me', $patch)
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation')
        ->assertJsonStructure(['errors' => [$field]]);
})->with([
    [['firstName' => '   '], 'firstName'],
    [['firstName' => null], 'firstName'],
    [['firstName' => str_repeat('a', 41)], 'firstName'],
    [['gender' => 'other'], 'gender'],
    [['birthDate' => '2026-02-30'], 'birthDate'],
    [['birthDate' => '1899-12-31'], 'birthDate'],
    [['birthDate' => now()->addDays(2)->format('Y-m-d')], 'birthDate'],
    [['email' => 'not-an-email'], 'email'],
    [['level' => 'guru'], 'level'],
    [['skills' => ['juggling']], 'skills.0'],
    [['skills' => ['knots', 'knots']], 'skills.1'],
    [['skills' => null], 'skills'],
    [['bio' => str_repeat('b', 161)], 'bio'],
    [['homeArea' => ['governorate' => 'paris']], 'homeArea.governorate'],
    [['homeArea' => ['governorate' => 'tunis', 'street' => 'x']], 'homeArea'],
    [['car' => ['seats' => 9]], 'car.seats'],
    [['car' => ['seats' => 0]], 'car.seats'],
    [['emergencyContact' => ['name' => 'Mounir', 'phone' => '98000111']], 'emergencyContact.phone'],
    [['emergencyContact' => ['phone' => '+21698000111']], 'emergencyContact.name'],
]);

it('counts characters, not bytes, for Arabic names', function () {
    $this->patchJson('/api/v1/me', ['firstName' => str_repeat('س', 40)])->assertOk();
    $this->patchJson('/api/v1/me', ['firstName' => str_repeat('س', 41)])->assertStatus(422);
});
