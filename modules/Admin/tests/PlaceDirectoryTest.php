<?php

use Illuminate\Support\Facades\Event;
use Modules\Admin\Places\Moderate;
use Modules\Shared\Places\PlaceDirectory;
use Modules\Shared\Places\PlaceModerated;
use Modules\Spots\Models\Spot;
use Modules\Trails\Models\Trail;

it('finds a place with its cover', function () {
    Trail::factory()->create(['id' => 'water-temple', 'governorate' => 'zaghouan']);

    $place = app(PlaceDirectory::class)->find('trail', 'water-temple');

    expect($place)->kind->toBe('trail')->id->toBe('water-temple')->governorate->toBe('zaghouan')
        ->status->toBe('live')->cover->toBeNull();
    expect(app(PlaceDirectory::class)->find('trail', 'nope'))->toBeNull();
    expect(app(PlaceDirectory::class)->find('lake', 'x'))->toBeNull();
});

it('submits a spot, live or pending, with the submitter recorded', function () {
    $directory = app(PlaceDirectory::class);
    $fields = ['name' => 'Oued Zitoun', 'description' => 'Olive grove by the river.', 'latitude' => 36.71,
        'longitude' => 9.12, 'governorate' => 'beja', 'type' => 'forest', 'access' => 'car', 'water' => true,
        'coverage' => 'weak', 'permit' => 'no'];

    $live = $directory->submit('spot', $fields, '01J000000000000000000USER1', true);
    $pending = $directory->submit('spot', $fields, '01J000000000000000000USER1', false);

    expect($live)->toStartWith('oued-zitoun-')->not->toBe($pending);
    expect(Spot::findOrFail($live))->status->toBe('live')->created_by->toBe('01J000000000000000000USER1')
        ->name->toBe(['ar' => 'Oued Zitoun', 'fr' => 'Oued Zitoun', 'en' => 'Oued Zitoun']);
    expect(Spot::findOrFail($pending)->status)->toBe('pending');
});

it('submits a trail with its facts', function () {
    $id = app(PlaceDirectory::class)->submit('trail', ['name' => 'Ras el Ain loop', 'description' => 'Short loop.',
        'latitude' => 36.4, 'longitude' => 10.1, 'governorate' => 'zaghouan', 'distanceKm' => 6.5,
        'elevationGainM' => 300, 'durationMin' => 150, 'difficulty' => 'easy', 'shape' => 'loop',
        'waterOnRoute' => false, 'coverage' => 'good', 'permit' => 'no'], '01J000000000000000000USER1', false);

    expect(Trail::findOrFail($id))->distance_km->toBe(6.5)->difficulty->toBe('easy')->status->toBe('pending');
});

it('announces moderation decisions', function () {
    Event::fake([PlaceModerated::class]);
    Trail::factory()->create(['id' => 'a', 'status' => 'pending']);
    Trail::factory()->create(['id' => 'b', 'status' => 'pending']);

    app(Moderate::class)->approve('trail', 'a');
    app(Moderate::class)->reject('trail', 'b', 'Duplicate');

    Event::assertDispatched(PlaceModerated::class, fn ($e) => $e->id === 'a' && $e->status === 'live');
    Event::assertDispatched(PlaceModerated::class, fn ($e) => $e->id === 'b' && $e->status === 'rejected');
});
