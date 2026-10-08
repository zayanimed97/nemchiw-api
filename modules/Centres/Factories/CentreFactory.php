<?php

namespace Modules\Centres\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centres\Models\Centre;
use Modules\Shared\Places\PlaceStatus;

/** @extends Factory<Centre> */
final class CentreFactory extends Factory
{
    protected $model = Centre::class;

    public function definition(): array
    {
        return [
            'id' => fake()->unique()->slug(2),
            'name' => ['ar' => 'مخيّم تجربة', 'fr' => 'Centre test', 'en' => 'Test centre'],
            'description' => ['ar' => 'وصف', 'fr' => 'Description', 'en' => 'Description'],
            'latitude' => 36.95,
            'longitude' => 8.75,
            'governorate' => 'jendouba',
            'amenities' => ['water', 'showers'],
            'price_from_tnd' => 30,
            'phone' => '+21698123456',
            'link' => 'https://instagram.com/example',
            'open_months' => [4, 5, 6, 7, 8, 9, 10],
            'status' => PlaceStatus::LIVE,
        ];
    }
}
