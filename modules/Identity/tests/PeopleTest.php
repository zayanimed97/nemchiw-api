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

it('lists missing trip-ready items in checklist order', function () {
    $u = User::factory()->create(['first_name' => 'Issa', 'last_name' => null, 'emergency_contact' => null]);

    expect(app(People::class)->readiness($u->id))
        ->missing->toBe(['phone', 'lastName', 'gender', 'birthDate', 'photo', 'emergencyContact'])
        ->ready->toBeFalse()
        ->tooYoung->toBeFalse();
});

it('reports readiness for a complete profile, and a too-young one', function () {
    $this->travelTo('2026-10-10');
    $user = tripReady();
    expect(app(People::class)->readiness($user->id))
        ->toBe(['ready' => true, 'missing' => [], 'tooYoung' => false, 'gender' => 'male', 'verifiedPhoto' => true]);

    $user->forceFill(['birth_date' => '2009-10-11'])->save();
    expect(app(People::class)->readiness($user->id))->ready->toBeFalse()->tooYoung->toBeTrue()->missing->toBe([]);
});

it('finds by verified phone and confirmed email only', function () {
    $verified = User::factory()->withPhone('+21698765432')->create(['email' => 'typed@x.tn']);
    $unverified = User::factory()->create(['phone' => '+21697000000', 'phone_verified_at' => null, 'email' => 'u@x.tn']);
    $confirmed = User::factory()->create(['email' => 'c@x.tn', 'confirmed_email' => 'c@x.tn']);
    $people = app(People::class);

    expect($people->findByContact('+21698765432'))->toBe($verified->id)
        ->and($people->findByContact('+21697000000'))->toBeNull()
        ->and($people->findByContact('typed@x.tn'))->toBeNull()
        ->and($people->findByContact('u@x.tn'))->toBeNull()
        ->and($people->findByContact('c@x.tn'))->toBe($confirmed->id);
});
