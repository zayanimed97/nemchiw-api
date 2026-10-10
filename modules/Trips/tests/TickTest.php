<?php

use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Trips\Actions\Tick;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

it('lapses unpaid and unanswered requests once the trip starts, blaming nobody', function () {
    $this->travelTo('2026-10-16 15:00:00');
    $trip = TripFactory::new()->create(['meetup_at' => '2026-10-16 15:30', 'starts_at' => '2026-10-16 16:00', 'ends_at' => '2026-10-18 13:00']);
    $pending = TripRequestFactory::new()->forTrip($trip)->create();
    $unsure = TripRequestFactory::new()->accepted()->forTrip($trip)->create();
    $joined = TripRequestFactory::new()->joined()->forTrip($trip)->create();

    app(Tick::class)();
    expect($pending->fresh()->status)->toBe('pending');

    $this->travelTo('2026-10-16 16:30:00');
    app(Tick::class)();

    expect($pending->fresh())->status->toBe('lapsed')->ended_by->toBe('system');
    expect($unsure->fresh())->status->toBe('lapsed')->code_secret->toBeNull();
    expect($joined->fresh()->status)->toBe('joined');
    expect(app(OrganiserRecords::class)->cards([$trip->organiser_id])[$trip->organiser_id]['record']['cancelledCampers'])->toBe(0);
});

it('finishes ended trips and credits the organiser with trips run and campers joined, once', function () {
    $this->travelTo('2026-10-10 10:00:00');
    $trip = TripFactory::new()->create(['starts_at' => '2026-10-16 16:00', 'ends_at' => '2026-10-18 13:00']);
    TripRequestFactory::new()->joined()->forTrip($trip)->count(3)->create();
    $empty = TripFactory::new()->create(['organiser_id' => $trip->organiser_id, 'starts_at' => '2026-10-16 16:00', 'ends_at' => '2026-10-18 13:00']);

    $this->travelTo('2026-10-18 14:00:00');
    app(Tick::class)();
    app(Tick::class)();

    expect($trip->fresh()->status)->toBe('finished');
    expect($empty->fresh()->status)->toBe('finished');
    expect(app(OrganiserRecords::class)->cards([$trip->organiser_id])[$trip->organiser_id]['record'])
        ->tripsRun->toBe(1)->campersJoined->toBe(3);
});

it('cancels drafts whose start passed while the place waited for review, without counting', function () {
    $this->travelTo('2026-10-10 10:00:00');
    $draft = TripFactory::new()->create(['status' => 'draft', 'starts_at' => '2026-10-11 08:00', 'ends_at' => '2026-10-11 12:00', 'meetup_at' => '2026-10-11 07:30']);

    $this->travelTo('2026-10-11 09:00:00');
    app(Tick::class)();

    expect($draft->fresh()->status)->toBe('cancelled');
    expect(app(OrganiserRecords::class)->cards([$draft->organiser_id])[$draft->organiser_id]['record']['cancelledTrips'])->toBe(0);
});
