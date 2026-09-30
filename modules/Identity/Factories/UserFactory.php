<?php

namespace Modules\Identity\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Models\User;

/** @extends Factory<User> */
final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return ['first_name' => fake()->firstName()];
    }

    public function withPhone(string $phone): static
    {
        return $this->state(fn () => ['phone' => $phone, 'phone_verified_at' => now()]);
    }
}
