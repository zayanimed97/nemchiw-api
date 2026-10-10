<?php

use Modules\Identity\Models\User;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

it('lists upcoming published trips by start date with seats taken', function () {
    $later = TripFactory::new()->create(['title' => 'Later', 'starts_at' => '2026-10-24 06:00', 'ends_at' => '2026-10-24 12:00']);
    $soon = TripFactory::new()->create(['title' => 'Soon', 'starts_at' => '2026-10-16 16:00', 'ends_at' => '2026-10-18 13:00']);
    TripFactory::new()->create(['title' => 'Past', 'starts_at' => '2026-10-01 06:00', 'ends_at' => '2026-10-01 12:00']);
    TripFactory::new()->create(['title' => 'Draft', 'status' => 'draft']);
    TripFactory::new()->create(['title' => 'Cancelled', 'status' => 'cancelled']);
    TripRequestFactory::new()->joined()->forTrip($soon)->count(2)->create();
    TripRequestFactory::new()->forTrip($soon)->create(['status' => 'accepted']);

    $response = $this->getJson('/api/v1/trips')->assertOk();

    expect(array_column($response->json('data'), 'title'))->toBe(['Soon', 'Later']);
    expect($response->json('data.0.seatsTaken'))->toBe(2);
    expect($response->json('data.0'))->not->toHaveKey('meetup');
    expect($response->json('nextCursor'))->toBeNull();
});

it('filters by kind, dates and women-only', function () {
    TripFactory::new()->create(['kind' => 'camping', 'starts_at' => '2026-10-16 16:00', 'ends_at' => '2026-10-17 10:00']);
    TripFactory::new()->create(['kind' => 'hike_pro', 'starts_at' => '2026-10-17 06:00', 'ends_at' => '2026-10-17 12:00', 'women_only' => true]);
    TripFactory::new()->create(['kind' => 'hike_community', 'price_tnd' => null, 'starts_at' => '2026-11-01 06:00', 'ends_at' => '2026-11-01 12:00']);

    expect($this->getJson('/api/v1/trips?kind=hike_pro')->json('data'))->toHaveCount(1);
    expect($this->getJson('/api/v1/trips?from=2026-10-17T00:00:00.000Z&to=2026-10-31T00:00:00.000Z')->json('data'))->toHaveCount(1);
    expect($this->getJson('/api/v1/trips?womenOnly=1')->json('data.0.kind'))->toBe('hike_pro');
});

it('pages with a cursor', function () {
    TripFactory::new()->count(25)->sequence(fn ($s) => ['starts_at' => now()->addDays($s->index + 1), 'ends_at' => now()->addDays($s->index + 2)])->create();

    $first = $this->getJson('/api/v1/trips')->assertOk();
    $second = $this->getJson('/api/v1/trips?cursor='.urlencode($first->json('nextCursor')))->assertOk();

    expect($first->json('data'))->toHaveCount(20);
    expect($second->json('data'))->toHaveCount(5);
    expect($second->json('nextCursor'))->toBeNull();
});

it('shows the meetup only to the organiser and accepted or joined campers', function () {
    $trip = TripFactory::new()->create();
    $pending = TripRequestFactory::new()->forTrip($trip)->create();
    $accepted = TripRequestFactory::new()->forTrip($trip)->create(['status' => 'accepted']);

    $this->getJson("/api/v1/trips/{$trip->id}")->assertOk()->assertJsonPath('meetup', null);
    $this->withToken(tokenFor(User::find($pending->leader_id)))->getJson("/api/v1/trips/{$trip->id}")
        ->assertJsonPath('meetup', null)->assertJsonPath('myRequest.status', 'pending');
    $this->withToken(tokenFor(User::find($accepted->leader_id)))->getJson("/api/v1/trips/{$trip->id}")
        ->assertJsonPath('meetup.url', $trip->meetup_url)->assertJsonPath('contact', null);
    $this->withToken(tokenFor(User::find($trip->organiser_id)))->getJson("/api/v1/trips/{$trip->id}")
        ->assertJsonPath('meetup.url', $trip->meetup_url);
});

it('hides drafts from everyone but their organiser', function () {
    $trip = TripFactory::new()->create(['status' => 'draft']);

    $this->getJson("/api/v1/trips/{$trip->id}")->assertNotFound();
    $this->withToken(tokenFor(User::find($trip->organiser_id)))->getJson("/api/v1/trips/{$trip->id}")->assertOk();
});
