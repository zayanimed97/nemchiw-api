<?php

namespace Modules\Trails\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Places\PlaceStatus;
use Modules\Trails\Models\Trail;

/** @extends Factory<Trail> */
final class TrailFactory extends Factory
{
    protected $model = Trail::class;

    public function definition(): array
    {
        $name = ['ar' => 'ثنيّة تجربة', 'fr' => 'Sentier test', 'en' => 'Test trail'];

        return [
            'id' => fake()->unique()->slug(2),
            'name' => $name,
            'description' => $name,
            'latitude' => 36.4,
            'longitude' => 10.1,
            'governorate' => 'zaghouan',
            'distance_km' => 8.5,
            'elevation_gain_m' => 420,
            'duration_min' => 210,
            'difficulty' => 'moderate',
            'shape' => 'loop',
            'water_on_route' => false,
            'coverage' => 'weak',
            'permit' => 'no',
            'status' => PlaceStatus::LIVE,
        ];
    }
}
