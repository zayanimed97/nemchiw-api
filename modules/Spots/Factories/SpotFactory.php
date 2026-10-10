<?php

namespace Modules\Spots\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Places\PlaceStatus;
use Modules\Spots\Models\Spot;

/** @extends Factory<Spot> */
final class SpotFactory extends Factory
{
    protected $model = Spot::class;

    public function definition(): array
    {
        $name = ['ar' => 'بلاصة تجربة', 'fr' => 'Spot test', 'en' => 'Test spot'];

        return [
            'id' => fake()->unique()->slug(2),
            'name' => $name,
            'description' => $name,
            'latitude' => 36.78,
            'longitude' => 8.69,
            'governorate' => 'jendouba',
            'type' => 'forest',
            'access' => 'car',
            'water' => true,
            'coverage' => 'weak',
            'permit' => 'no',
            'status' => PlaceStatus::LIVE,
        ];
    }
}
