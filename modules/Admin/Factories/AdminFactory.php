<?php

namespace Modules\Admin\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Admin\Models\Admin;

/** @extends Factory<Admin> */
final class AdminFactory extends Factory
{
    protected $model = Admin::class;

    public function definition(): array
    {
        return [
            'name' => 'Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            // Any base32 secret: tests act as an admin who already set up two-factor.
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ];
    }
}
