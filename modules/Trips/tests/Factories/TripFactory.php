<?php

namespace Modules\Trips\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Trips\Models\Trip;

/** @extends Factory<Trip> */
final class TripFactory extends Factory
{
    protected $model = Trip::class;

    public function definition(): array
    {
        return [
            'organiser_id' => fn () => tripReady(['first_name' => 'Momen'])->id,
            'kind' => 'camping',
            'title' => 'Two nights under the cork oaks',
            'description' => 'Tents, fire, stars.',
            'destination_kind' => 'spot',
            'destination_id' => 'ain-draham',
            'destination' => ['name' => ['ar' => 'عين دراهم', 'fr' => 'Aïn Draham', 'en' => 'Ain Draham'], 'governorate' => 'jendouba', 'latitude' => 36.78, 'longitude' => 8.69, 'cover' => null],
            'meetup_url' => 'https://maps.app.goo.gl/abc123',
            'meetup_at' => now()->addDays(6)->setTime(15, 30),
            'starts_at' => now()->addDays(6)->setTime(16, 0),
            'ends_at' => now()->addDays(8)->setTime(13, 0),
            'seats' => 8,
            'price_tnd' => 120,
            'women_only' => false,
            'status' => 'published',
        ];
    }
}
