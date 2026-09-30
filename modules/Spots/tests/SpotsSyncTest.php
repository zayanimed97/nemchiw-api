<?php

use Modules\Spots\Actions\ImportSpots;
use Modules\Spots\Models\Spot;

beforeEach(function () {
    $this->freezeTime();
    app(ImportSpots::class)(json_decode(file_get_contents(base_path('modules/Spots/database/data/spots.json')), true));
});

it('returns every spot in the contract shape', function () {
    $response = $this->getJson('/api/v1/spots')->assertOk();

    expect($response->json('data'))->toHaveCount(15);
    expect(array_keys($response->json('data.0')))->toEqualCanonicalizing([
        'id', 'name', 'description', 'latitude', 'longitude', 'governorate', 'type',
        'access', 'water', 'coverage', 'permit', 'updatedAt',
    ]);
    expect($response->json('data.0.name'))->toHaveKeys(['ar', 'fr', 'en']);
    expect($response->json('serverTime'))->toMatch('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/');
});

it('returns only spots changed after updatedSince', function () {
    $since = $this->getJson('/api/v1/spots')->json('serverTime');

    $this->travel(5)->minutes();
    $spot = Spot::findOrFail('tabarka');
    $spot->water = ! $spot->water;
    $spot->save();

    $this->getJson('/api/v1/spots?updatedSince='.urlencode($since))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 'tabarka');
});

it('treats updatedSince as strictly after', function () {
    $newest = $this->getJson('/api/v1/spots')->json('data.14.updatedAt');

    $this->getJson('/api/v1/spots?updatedSince='.urlencode($newest))->assertJsonCount(0, 'data');
});

it('understands offsets in updatedSince', function () {
    $cutoff = Spot::orderBy('updated_at')->skip(4)->first()->updated_at;
    $utc = $this->getJson('/api/v1/spots?updatedSince='.urlencode($cutoff->utc()->format('Y-m-d\TH:i:s.v\Z')))->json('data');
    $offset = $this->getJson('/api/v1/spots?updatedSince='.urlencode($cutoff->setTimezone('+01:00')->format('Y-m-d\TH:i:s.vP')))->json('data');

    expect($utc)->toHaveCount(10);
    expect($offset)->toEqual($utc);
});

it('pages with serverTime as the cursor', function () {
    config(['spots.page_size' => 10]);

    $first = $this->getJson('/api/v1/spots')->assertJsonCount(10, 'data');
    expect($first->json('serverTime'))->toBe($first->json('data.9.updatedAt'));

    $second = $this->getJson('/api/v1/spots?updatedSince='.urlencode($first->json('serverTime')))->assertJsonCount(5, 'data');
    $ids = array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id'));
    expect(array_unique($ids))->toHaveCount(15);
});

it('rejects a malformed updatedSince', function () {
    $this->getJson('/api/v1/spots?updatedSince=not-a-date')->assertStatus(422)->assertJsonPath('code', 'validation');
});

it('answers a repeat request with 304 via ETag', function () {
    $etag = $this->getJson('/api/v1/spots')->headers->get('ETag');
    expect($etag)->not->toBeNull();
    $this->getJson('/api/v1/spots', ['If-None-Match' => $etag])->assertStatus(304);
});
