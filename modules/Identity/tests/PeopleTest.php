<?php

use Modules\Identity\Contracts\People;
use Modules\Identity\Models\User;
use Tests\Support\FakeProfilePhotos;

it('says a complete adult profile is trip-ready', function () {
    $this->travelTo('2026-10-10');
    $user = tripReady(['gender' => 'female']);

    expect(app(People::class)->tripReadiness($user->id))
        ->toBe(['ready' => true, 'gender' => 'female', 'verifiedPhoto' => true]);
});

it('needs every required item and an age of 18', function (array $change) {
    $this->travelTo('2026-10-10');
    $user = tripReady();
    $user->forceFill($change)->save();

    expect(app(People::class)->tripReadiness($user->id)['ready'])->toBeFalse();
})->with([
    'no last name' => [['last_name' => null]],
    'no gender' => [['gender' => null]],
    'no birth date' => [['birth_date' => null]],
    'seventeen' => [['birth_date' => '2009-10-11']],
    'no phone' => [['phone' => null, 'phone_verified_at' => null]],
    'no emergency contact' => [['emergency_contact' => null]],
]);

it('needs a verified photo', function () {
    $user = tripReady();
    FakeProfilePhotos::$verified = [];

    expect(app(People::class)->tripReadiness($user->id))->ready->toBeFalse()->verifiedPhoto->toBeFalse();
});

it('gives participant details with the private fields', function () {
    $this->travelTo('2026-10-10');
    $user = tripReady(['first_name' => 'Sami', 'last_name' => 'Ben Salah', 'gender' => 'male']);
    $user->forceFill(['phone' => '+21620000001', 'medical_info' => ['note' => 'Asthma']])->save();

    expect(app(People::class)->participantDetails($user->id))->toBe([
        'firstName' => 'Sami', 'lastName' => 'Ben Salah', 'gender' => 'male', 'age' => 31,
        'phone' => '+21620000001', 'emergencyContact' => ['name' => 'Leila', 'phone' => '+21620000002'],
        'medicalInfo' => ['note' => 'Asthma'],
    ]);
});

it('gives public profiles keyed by id, skipping unknown ids', function () {
    $a = User::factory()->create(['first_name' => 'Amira']);

    $profiles = app(People::class)->publicProfiles([$a->id, '01UNKNOWN']);

    expect(array_keys($profiles))->toBe([$a->id]);
    expect($profiles[$a->id]['firstName'])->toBe('Amira');
    expect($profiles[$a->id])->not->toHaveKey('lastName');
});
