<?php

use Modules\Identity\Models\User;
use Modules\Organisers\Models\Organiser;
use Modules\Spots\Models\Spot;
use Modules\Trails\Models\Trail;
use Modules\Trips\Models\Trip;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(function () {
    $this->travelTo('2026-10-10 10:00:00');
    Spot::factory()->create(['id' => 'ain-draham', 'governorate' => 'jendouba']);
    Trail::factory()->create(['id' => 'water-temple']);
});

function certified(array $attributes = []): User
{
    $user = tripReady($attributes);
    Organiser::query()->create(['user_id' => $user->id, 'certified_at' => now(), 'public_phone' => '+21671000000']);

    return $user;
}

function campingPayload(array $overrides = []): array
{
    return array_merge([
        'kind' => 'camping', 'title' => 'Two nights under the cork oaks', 'description' => 'Tents, fire, stars.',
        'destination' => ['kind' => 'spot', 'id' => 'ain-draham'],
        'meetupUrl' => 'https://maps.app.goo.gl/abc123', 'meetupAt' => '2026-10-16T15:30:00.000Z',
        'startsAt' => '2026-10-16T16:00:00.000Z', 'endsAt' => '2026-10-18T13:00:00.000Z',
        'seats' => 8, 'priceTnd' => 120, 'womenOnly' => false,
    ], $overrides);
}

it('lets a certified organiser publish a camping trip with a destination snapshot', function () {
    $momen = certified(['first_name' => 'Momen']);

    $response = $this->withToken(tokenFor($momen))->postJson('/api/v1/trips', campingPayload())->assertCreated();

    $response->assertJsonPath('status', 'published')
        ->assertJsonPath('destination.id', 'ain-draham')
        ->assertJsonPath('destination.governorate', 'jendouba')
        ->assertJsonPath('organiser.firstName', 'Momen')
        ->assertJsonPath('organiser.certified', true)
        ->assertJsonPath('seatsTaken', 0)
        ->assertJsonPath('meetup.url', 'https://maps.app.goo.gl/abc123')
        ->assertJsonPath('myRequest', null);
    expect(Trip::query()->sole()->destination['name']['en'])->toBe('Test spot');
});

it('keeps certified kinds for certified organisers', function () {
    $user = tripReady();

    $this->withToken(tokenFor($user))->postJson('/api/v1/trips', campingPayload())
        ->assertStatus(403)->assertJsonPath('code', 'not_certified');
    $this->withToken(tokenFor($user))->postJson('/api/v1/trips', campingPayload(['kind' => 'hike_pro']))
        ->assertStatus(403);
});

it('lets any trip-ready person host a free community hike', function () {
    $user = tripReady();

    $this->withToken(tokenFor($user))->postJson('/api/v1/trips', campingPayload([
        'kind' => 'hike_community', 'destination' => ['kind' => 'trail', 'id' => 'water-temple'], 'priceTnd' => null,
    ]))->assertCreated()->assertJsonPath('priceTnd', null);
});

it('refuses a price on a community hike and requires one on certified kinds', function () {
    $this->withToken(tokenFor(tripReady()))->postJson('/api/v1/trips', campingPayload(['kind' => 'hike_community', 'priceTnd' => 10]))
        ->assertStatus(422)->assertJsonPath('code', 'validation');
    $this->withToken(tokenFor(certified()))->postJson('/api/v1/trips', campingPayload(['priceTnd' => null]))
        ->assertStatus(422);
});

it('needs a trip-ready host', function () {
    $user = User::factory()->create();

    $this->withToken(tokenFor($user))->postJson('/api/v1/trips', campingPayload(['kind' => 'hike_community', 'priceTnd' => null]))
        ->assertStatus(422)->assertJsonPath('code', 'not_trip_ready');
});

it('validates dates, seats and the meetup link', function (array $change) {
    $this->withToken(tokenFor(certified()))->postJson('/api/v1/trips', campingPayload($change))
        ->assertStatus(422)->assertJsonPath('code', 'validation');
})->with([
    'starts in the past' => [['startsAt' => '2026-10-09T10:00:00.000Z', 'meetupAt' => '2026-10-09T09:00:00.000Z']],
    'ends before it starts' => [['endsAt' => '2026-10-16T15:00:00.000Z']],
    'meetup after the start' => [['meetupAt' => '2026-10-16T17:00:00.000Z']],
    'no seats' => [['seats' => 0]],
    'too many seats' => [['seats' => 21]],
    'plain http link' => [['meetupUrl' => 'http://maps.app.goo.gl/abc']],
    'unknown destination' => [['destination' => ['kind' => 'spot', 'id' => 'nowhere']]],
    'title too long' => [['title' => str_repeat('a', 81)]],
]);

it('keeps a trip to someone else\'s pending place out', function () {
    Spot::factory()->create(['id' => 'secret-cove', 'status' => 'pending', 'created_by' => '01J00000000000000000OTHER0']);

    $this->withToken(tokenFor(certified()))->postJson('/api/v1/trips', campingPayload(['destination' => ['kind' => 'spot', 'id' => 'secret-cove']]))
        ->assertStatus(422);
});

it('holds a trip to the host\'s own pending place as a draft', function () {
    $host = tripReady();
    Spot::factory()->create(['id' => 'my-cove', 'status' => 'pending', 'created_by' => $host->id]);

    $this->withToken(tokenFor($host))->postJson('/api/v1/trips', campingPayload([
        'kind' => 'hike_community', 'priceTnd' => null, 'destination' => ['kind' => 'spot', 'id' => 'my-cove'],
    ]))->assertCreated()->assertJsonPath('status', 'draft');
});

it('lets only the organiser edit, and locks dates, price and place once someone joined', function () {
    $momen = certified();
    $trip = TripFactory::new()->create(['organiser_id' => $momen->id, 'seats' => 8]);

    $this->withToken(tokenFor(tripReady()))->patchJson("/api/v1/trips/{$trip->id}", ['title' => 'Mine now'])
        ->assertStatus(403)->assertJsonPath('code', 'forbidden');
    $this->withToken(tokenFor($momen))->patchJson("/api/v1/trips/{$trip->id}", ['priceTnd' => 150])
        ->assertOk()->assertJsonPath('priceTnd', 150);

    TripRequestFactory::new()->joined()->forTrip($trip)->create();
    $this->withToken(tokenFor($momen))->patchJson("/api/v1/trips/{$trip->id}", ['priceTnd' => 200])
        ->assertStatus(409)->assertJsonPath('code', 'trip_locked');
    $this->withToken(tokenFor($momen))->patchJson("/api/v1/trips/{$trip->id}", ['description' => 'Bring a head torch.'])
        ->assertOk();
    $this->withToken(tokenFor($momen))->patchJson("/api/v1/trips/{$trip->id}", ['seats' => 0])
        ->assertStatus(422);
});
