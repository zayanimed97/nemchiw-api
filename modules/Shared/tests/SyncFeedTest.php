<?php

use Modules\Shared\Places\PlaceStatus;
use Modules\Spots\Actions\ImportSpots;
use Modules\Spots\Models\Spot;

beforeEach(function () {
    $this->freezeTime();
    app(ImportSpots::class)(json_decode(file_get_contents(base_path('modules/Spots/database/data/spots.json')), true));
});

function cursorNow($test): string
{
    return $test->getJson('/api/v1/spots')->json('serverTime');
}

it('answers a first sync with the full live set', function () {
    Spot::findOrFail('tabarka')->update(['status' => PlaceStatus::HIDDEN]);

    $response = $this->getJson('/api/v1/spots')->assertOk();

    expect($response->json('full'))->toBeTrue();
    expect($response->json('removed'))->toBe([]);
    expect(array_column($response->json('data'), 'id'))->toHaveCount(14)->not->toContain('tabarka');
});

it('lists hidden, rejected and deleted places in removed', function () {
    $this->travel(10)->minutes();
    $since = cursorNow($this);
    $this->travel(10)->minutes();

    Spot::findOrFail('tabarka')->update(['status' => PlaceStatus::HIDDEN]);
    Spot::findOrFail('ain-draham')->update(['status' => PlaceStatus::REJECTED, 'rejection_reason' => 'Duplicate']);
    Spot::findOrFail('zaghouan')->delete();

    $response = $this->getJson('/api/v1/spots?updatedSince='.urlencode($since))->assertOk();

    expect($response->json('full'))->toBeFalse();
    expect($response->json('data'))->toBe([]);
    expect($response->json('removed'))->toEqualCanonicalizing(['tabarka', 'ain-draham', 'zaghouan']);
});

it('never sends pending places', function () {
    $this->travel(10)->minutes();
    $since = cursorNow($this);
    $this->travel(10)->minutes();
    Spot::findOrFail('tabarka')->update(['status' => PlaceStatus::PENDING]);

    $incremental = $this->getJson('/api/v1/spots?updatedSince='.urlencode($since));
    expect(array_column($incremental->json('data'), 'id'))->not->toContain('tabarka');
    expect($incremental->json('removed'))->toBe(['tabarka']);
    expect(array_column($this->getJson('/api/v1/spots')->json('data'), 'id'))->not->toContain('tabarka');
});

it('sends a full set when the cursor is older than the tombstone window', function () {
    $old = now()->subDays(91)->utc()->format('Y-m-d\TH:i:s.v\Z');
    Spot::findOrFail('tabarka')->delete();

    $response = $this->getJson('/api/v1/spots?updatedSince='.urlencode($old))->assertOk();

    expect($response->json('full'))->toBeTrue();
    expect($response->json('data'))->toHaveCount(14);
    expect($response->json('removed'))->toBe([]);
});

it('brings a restored place back as data', function () {
    Spot::findOrFail('tabarka')->delete();
    $this->travel(10)->minutes();
    $since = cursorNow($this);
    $this->travel(10)->minutes();
    Spot::withTrashed()->findOrFail('tabarka')->restore();

    $response = $this->getJson('/api/v1/spots?updatedSince='.urlencode($since));
    expect(array_column($response->json('data'), 'id'))->toBe(['tabarka']);
    expect($response->json('removed'))->toBe([]);
});
