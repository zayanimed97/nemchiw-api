<?php

use Modules\Trips\Actions\Tick;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

it('counts finished trips the person scanned into on their card', function () {
    $this->travelTo('2026-10-10 10:00:00');
    $sami = tripReady();
    $done = TripFactory::new()->create(['starts_at' => '2026-10-11 08:00', 'ends_at' => '2026-10-11 12:00', 'meetup_at' => '2026-10-11 07:30']);
    TripRequestFactory::new()->joined()->forTrip($done)->create(['leader_id' => $sami->id]);
    $upcoming = TripFactory::new()->create();
    TripRequestFactory::new()->joined()->forTrip($upcoming)->create(['leader_id' => $sami->id]);

    $this->travelTo('2026-10-12 10:00:00');
    app(Tick::class)();

    $this->withToken(tokenFor($sami))->getJson('/api/v1/me')->assertJsonPath('campsCount', 1);
});
