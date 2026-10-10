<?php

use Modules\Admin\Places\Moderate;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Spots\Models\Spot;
use Modules\Trips\Tests\Factories\TripFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

it('publishes a draft trip when its place is approved', function () {
    Spot::factory()->create(['id' => 'my-cove', 'status' => 'pending']);
    $draft = TripFactory::new()->create(['status' => 'draft', 'destination_kind' => 'spot', 'destination_id' => 'my-cove']);

    app(Moderate::class)->approve('spot', 'my-cove');

    expect($draft->fresh()->status)->toBe('published');
});

it('cancels a draft trip without penalty when its place is rejected', function () {
    Spot::factory()->create(['id' => 'my-cove', 'status' => 'pending']);
    $draft = TripFactory::new()->create(['status' => 'draft', 'destination_kind' => 'spot', 'destination_id' => 'my-cove']);

    app(Moderate::class)->reject('spot', 'my-cove', 'Private land');

    expect($draft->fresh()->status)->toBe('cancelled');
    expect(app(OrganiserRecords::class)->cards([$draft->organiser_id])[$draft->organiser_id]['record']['cancelledTrips'])->toBe(0);
});
