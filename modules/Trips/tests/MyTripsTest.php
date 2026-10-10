<?php

use Modules\Identity\Models\User;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

it('lists going and hosting trips with the server time', function () {
    $sami = tripReady();
    $going = TripRequestFactory::new()->create(['leader_id' => $sami->id]);
    $hosted = TripFactory::new()->create(['organiser_id' => $sami->id]);

    $response = $this->withToken(tokenFor($sami))->getJson('/api/v1/me/trips')->assertOk();

    expect($response->json('going.0.id'))->toBe($going->trip_id);
    expect($response->json('going.0.myRequest.status'))->toBe('pending');
    expect($response->json('hosting.0.trip.id'))->toBe($hosted->id);
    expect($response->json('serverTime'))->toBe('2026-10-10T10:00:00.000Z');
});

it('gives the organiser the code secret only while a request is accepted', function () {
    $trip = TripFactory::new()->create();
    $accepted = TripRequestFactory::new()->accepted()->forTrip($trip)->create();
    TripRequestFactory::new()->forTrip($trip)->create();

    $requests = collect($this->withToken(tokenFor(User::find($trip->organiser_id)))->getJson('/api/v1/me/trips')->json('hosting.0.requests'));

    expect($requests->firstWhere('id', $accepted->id)['codeSecret'])->toBe($accepted->code_secret);
    expect($requests->firstWhere('status', 'pending')['codeSecret'])->toBeNull();
    expect($this->withToken(tokenFor(User::find($accepted->leader_id)))->getJson('/api/v1/me/trips')->json())
        ->not->toHaveKey('going.0.codeSecret');
    expect(json_encode($this->withToken(tokenFor(User::find($accepted->leader_id)))->getJson('/api/v1/me/trips')->json()))
        ->not->toContain($accepted->code_secret);
});

it('shows private details only for joined campers, until 14 days after the trip', function () {
    $trip = TripFactory::new()->create(['starts_at' => '2026-10-16 16:00', 'ends_at' => '2026-10-18 13:00']);
    $camper = tripReady(['last_name' => 'Ben Salah']);
    TripRequestFactory::new()->joined()->forTrip($trip)->create(['leader_id' => $camper->id]);
    $unsure = TripRequestFactory::new()->accepted()->forTrip($trip)->create();
    $organiserToken = tokenFor(User::find($trip->organiser_id));
    $details = fn () => collect($this->withToken($organiserToken)->getJson('/api/v1/me/trips')->json('hosting.0.requests'))
        ->mapWithKeys(fn ($r) => [$r['leader']['id'] => $r['details']]);

    expect($details()[$camper->id]['lastName'])->toBe('Ben Salah');
    expect($details()[$unsure->leader_id])->toBeNull();

    $this->travelTo('2026-11-01 12:59:00');
    expect($details()[$camper->id])->not->toBeNull();
    $this->travelTo('2026-11-01 13:01:00');
    expect($this->withToken($organiserToken)->getJson('/api/v1/me/trips')->json('hosting'))->toBe([]);
});

it('tells the organiser about each camper\'s history', function () {
    $camper = tripReady();
    TripRequestFactory::new()->joined()->create(['leader_id' => $camper->id]);
    TripRequestFactory::new()->create(['leader_id' => $camper->id, 'status' => 'withdrawn', 'withdrawn_from' => 'joined']);
    TripRequestFactory::new()->create(['leader_id' => $camper->id, 'status' => 'withdrawn', 'withdrawn_from' => 'pending']);
    $trip = TripFactory::new()->create();
    TripRequestFactory::new()->forTrip($trip)->create(['leader_id' => $camper->id]);

    $history = $this->withToken(tokenFor(User::find($trip->organiser_id)))->getJson('/api/v1/me/trips')->json('hosting.0.requests.0.history');

    expect($history)->toBe(['tripsJoined' => 1, 'withdrawals' => ['pending' => 1, 'accepted' => 0, 'joined' => 1]]);
});

it('gives a joined camper the organiser\'s phone', function () {
    $trip = TripFactory::new()->create();
    User::find($trip->organiser_id)->forceFill(['phone' => '+21620000099'])->save();
    $request = TripRequestFactory::new()->joined()->forTrip($trip)->create();

    $this->withToken(tokenFor(User::find($request->leader_id)))->getJson('/api/v1/me/trips')
        ->assertJsonPath('going.0.contact.phone', '+21620000099');
});
