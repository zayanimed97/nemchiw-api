<?php

use Modules\Trips\Contracts\TripRequests;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

it('describes requests and their trips for other modules', function () {
    $trip = TripFactory::new()->create(['title' => 'Cork oaks']);
    $request = TripRequestFactory::new()->forTrip($trip)->accepted()->create();
    $joined = TripRequestFactory::new()->forTrip($trip)->joined()->create();

    $context = app(TripRequests::class)->context([$request->id, 'missing']);

    expect(array_keys($context))->toBe([$request->id]);
    expect($context[$request->id])->toMatchArray([
        'tripId' => $trip->id, 'tripTitle' => 'Cork oaks', 'tripKind' => 'camping', 'tripStatus' => 'published',
        'requestStatus' => 'accepted', 'leaderId' => $request->leader_id, 'organiserId' => $trip->organiser_id,
        'startsAt' => '2026-10-16T16:00:00.000Z',
    ]);
    expect(app(TripRequests::class)->trip($trip->id))->toMatchArray(['title' => 'Cork oaks', 'organiserId' => $trip->organiser_id]);
    expect(app(TripRequests::class)->requestsOfTrip($trip->id, ['joined']))
        ->toBe([['requestId' => $joined->id, 'leaderId' => $joined->leader_id, 'status' => 'joined']]);
});
