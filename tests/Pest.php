<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Arch', '../modules/*/tests');

function tokenFor(User $user): string
{
    return $user->createToken('test', ['*'], now()->addDays(90))->plainTextToken;
}
