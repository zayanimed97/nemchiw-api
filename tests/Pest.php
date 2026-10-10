<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Contracts\ProfilePhotos;
use Modules\Identity\Models\User;
use Tests\Support\FakeProfilePhotos;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Arch', '../modules/*/tests');

function tokenFor(User $user): string
{
    return $user->createToken('test', ['*'], now()->addDays(90))->plainTextToken;
}

/** A user who passes the trip-ready check (verified photo through FakeProfilePhotos). */
function tripReady(array $attributes = []): User
{
    static $sequence = 0;
    $sequence++;
    $user = User::factory()->withPhone(sprintf('+2162%07d', $sequence))->create(array_merge([
        'first_name' => 'Sami', 'last_name' => 'Ben Salah', 'gender' => 'male', 'birth_date' => '1995-01-01',
        'emergency_contact' => ['name' => 'Leila', 'phone' => '+21620000002'],
    ], $attributes));
    if (! app(ProfilePhotos::class) instanceof FakeProfilePhotos) {
        FakeProfilePhotos::$verified = [];
        app()->instance(ProfilePhotos::class, new FakeProfilePhotos);
    }
    FakeProfilePhotos::$verified[] = $user->id;

    return $user;
}
