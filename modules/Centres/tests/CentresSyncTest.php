<?php

use Modules\Centres\Models\Centre;
use Modules\Shared\Places\PlaceStatus;

beforeEach(fn () => $this->freezeTime());

it('returns live centres in the contract shape', function () {
    Centre::factory()->create(['id' => 'ain-soltane', 'price_from_tnd' => null, 'link' => null]);
    Centre::factory()->create(['id' => 'pending-one', 'status' => PlaceStatus::PENDING]);

    $response = $this->getJson('/api/v1/centres')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect(array_keys($response->json('data.0')))->toEqualCanonicalizing([
        'id', 'name', 'description', 'latitude', 'longitude', 'governorate', 'amenities',
        'priceFromTnd', 'phone', 'link', 'openMonths', 'photos', 'updatedAt',
    ]);
    expect($response->json('data.0.priceFromTnd'))->toBeNull();
    expect($response->json('data.0.link'))->toBeNull();
    expect($response->json('data.0.amenities'))->toBe(['water', 'showers']);
    expect($response->json('data.0.openMonths'))->toBe([4, 5, 6, 7, 8, 9, 10]);
});

it('sends the price as a number', function () {
    Centre::factory()->create(['price_from_tnd' => 25]);

    expect($this->getJson('/api/v1/centres')->json('data.0.priceFromTnd'))->toEqual(25.0);
});
