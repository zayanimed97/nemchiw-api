<?php

use Illuminate\Support\Facades\Storage;
use Modules\Media\Contracts\PlacePhotos;
use Modules\Media\Testing\Images;
use Modules\Shared\Places\PlaceStatus;
use Modules\Trails\Models\Trail;

beforeEach(function () {
    $this->freezeTime();
    Storage::fake('place_photos');
});

it('returns live trails in the contract shape', function () {
    Trail::factory()->create(['id' => 'zaghouan-aqueduct']);
    Trail::factory()->create(['id' => 'hidden-one', 'status' => PlaceStatus::HIDDEN]);

    $response = $this->getJson('/api/v1/trails')->assertOk();

    expect($response->json('full'))->toBeTrue();
    expect($response->json('data'))->toHaveCount(1);
    expect(array_keys($response->json('data.0')))->toEqualCanonicalizing([
        'id', 'name', 'description', 'latitude', 'longitude', 'governorate', 'distanceKm',
        'elevationGainM', 'durationMin', 'difficulty', 'shape', 'waterOnRoute', 'coverage',
        'permit', 'photos', 'updatedAt',
    ]);
    expect($response->json('data.0.distanceKm'))->toBeFloat();
    expect($response->json('data.0.elevationGainM'))->toBeInt();
    expect($response->json('data.0.waterOnRoute'))->toBeBool();
});

it('reports removed trails and resyncs on photo changes', function () {
    Trail::factory()->create(['id' => 'a']);
    Trail::factory()->create(['id' => 'b']);
    $this->travel(10)->minutes();
    $since = $this->getJson('/api/v1/trails')->json('serverTime');
    $this->travel(10)->minutes();

    Trail::findOrFail('a')->delete();
    $hash = app(PlacePhotos::class)->add('trail', 'b', Images::jpeg());

    $response = $this->getJson('/api/v1/trails?updatedSince='.urlencode($since));
    expect($response->json('removed'))->toBe(['a']);
    expect($response->json('data.0.id'))->toBe('b');
    expect($response->json('data.0.photos'))->toBe([$hash]);
});
