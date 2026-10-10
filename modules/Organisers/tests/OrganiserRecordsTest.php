<?php

use Modules\Organisers\Contracts\OrganiserRecords;

it('counts per organiser and answers for unknown people with zeros', function () {
    $records = app(OrganiserRecords::class);
    $momen = tripReady(['first_name' => 'Momen']);

    $records->bump($momen->id, 'trips_run');
    $records->bump($momen->id, 'campers_joined', 7);
    $records->bump($momen->id, 'cancelled_campers');

    expect($records->cards([$momen->id, '01UNKNOWN']))->toBe([
        $momen->id => ['certified' => false, 'record' => ['tripsRun' => 1, 'campersJoined' => 7, 'cancelledTrips' => 0, 'cancelledCampers' => 1]],
        '01UNKNOWN' => ['certified' => false, 'record' => ['tripsRun' => 0, 'campersJoined' => 0, 'cancelledTrips' => 0, 'cancelledCampers' => 0]],
    ]);
});

it('refuses an unknown counter', function () {
    app(OrganiserRecords::class)->bump('01X', 'reviews');
})->throws(InvalidArgumentException::class);

it('shows a public organiser card', function () {
    $momen = tripReady(['first_name' => 'Momen']);
    app(OrganiserRecords::class)->bump($momen->id, 'trips_run', 12);

    $this->getJson("/api/v1/organisers/{$momen->id}")
        ->assertOk()
        ->assertJsonPath('firstName', 'Momen')
        ->assertJsonPath('certified', false)
        ->assertJsonPath('record.tripsRun', 12)
        ->assertJsonMissingPath('lastName');
    $this->getJson('/api/v1/organisers/01UNKNOWN')->assertNotFound();
});
