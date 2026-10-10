<?php

namespace Modules\Trips\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Models\TripRequestMember;

/** @extends Factory<TripRequest> */
final class TripRequestFactory extends Factory
{
    protected $model = TripRequest::class;

    public function definition(): array
    {
        return [
            'trip_id' => fn () => TripFactory::new()->create()->id,
            'leader_id' => fn () => tripReady()->id,
            'note' => 'Salam! I have my own tent.',
            'status' => 'pending',
        ];
    }

    public function forTrip(Trip $trip): static
    {
        return $this->state(['trip_id' => $trip->id]);
    }

    public function accepted(): static
    {
        return $this->state(['status' => 'accepted', 'accepted_at' => now()])
            ->afterCreating(fn (TripRequest $r) => $r->forceFill(['code_secret' => base64_encode(random_bytes(32))])->save());
    }

    public function joined(): static
    {
        return $this->state(['status' => 'joined', 'accepted_at' => now(), 'joined_at' => now()])
            ->afterCreating(function (TripRequest $r) {
                $r->forceFill(['code_secret' => base64_encode(random_bytes(32))])->save();
                TripRequestMember::query()->updateOrCreate(['request_id' => $r->id, 'user_id' => $r->leader_id], ['scanned_at' => now()]);
            });
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (TripRequest $r) => TripRequestMember::query()->firstOrCreate(['request_id' => $r->id, 'user_id' => $r->leader_id]));
    }
}
