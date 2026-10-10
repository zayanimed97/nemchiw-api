<?php

use Modules\Identity\Models\User;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

function record(string $userId): array
{
    return app(OrganiserRecords::class)->cards([$userId])[$userId]['record'];
}

it('asks to join with a note and sees the request on the trip', function () {
    $trip = TripFactory::new()->create();
    $sami = tripReady();

    $this->withToken(tokenFor($sami))->postJson("/api/v1/trips/{$trip->id}/requests", ['note' => 'I have my own tent.'])
        ->assertCreated()
        ->assertJsonPath('myRequest.status', 'pending')
        ->assertJsonPath('myRequest.note', 'I have my own tent.');
});

it('refuses the organiser, people who are not trip-ready, and a second open request', function () {
    $trip = TripFactory::new()->create();
    $sami = tripReady();

    $this->withToken(tokenFor(User::find($trip->organiser_id)))->postJson("/api/v1/trips/{$trip->id}/requests")
        ->assertStatus(422)->assertJsonPath('code', 'own_trip');
    $this->withToken(tokenFor(User::factory()->create()))->postJson("/api/v1/trips/{$trip->id}/requests")
        ->assertStatus(422)->assertJsonPath('code', 'not_trip_ready');
    $this->withToken(tokenFor($sami))->postJson("/api/v1/trips/{$trip->id}/requests")->assertCreated();
    $this->withToken(tokenFor($sami))->postJson("/api/v1/trips/{$trip->id}/requests")
        ->assertStatus(409)->assertJsonPath('code', 'already_requested');
});

it('keeps women-only trips for women with a verified photo', function () {
    $trip = TripFactory::new()->create(['women_only' => true]);

    $this->withToken(tokenFor(tripReady(['gender' => 'male'])))->postJson("/api/v1/trips/{$trip->id}/requests")
        ->assertStatus(403)->assertJsonPath('code', 'women_only_required');
    $this->withToken(tokenFor(tripReady(['gender' => 'female'])))->postJson("/api/v1/trips/{$trip->id}/requests")
        ->assertCreated();
});

it('refuses requests on trips that started, are drafts, or are cancelled', function (array $state) {
    $trip = TripFactory::new()->create($state);

    $this->withToken(tokenFor(tripReady()))->postJson("/api/v1/trips/{$trip->id}/requests")
        ->assertStatus(409)->assertJsonPath('code', 'trip_not_open');
})->with([
    'started' => [['starts_at' => '2026-10-10 09:00', 'ends_at' => '2026-10-11 09:00']],
    'cancelled' => [['status' => 'cancelled']],
]);

it('lets only the organiser accept, and gives the request a code secret', function () {
    $request = TripRequestFactory::new()->create();
    $organiser = User::find($request->trip->organiser_id);

    $this->withToken(tokenFor(User::find($request->leader_id)))->postJson("/api/v1/requests/{$request->id}/accept")
        ->assertStatus(403)->assertJsonPath('code', 'forbidden');
    $this->withToken(tokenFor($organiser))->postJson("/api/v1/requests/{$request->id}/accept")->assertOk();

    $request->refresh();
    expect($request->status)->toBe('accepted');
    expect(strlen(base64_decode($request->code_secret, true)))->toBe(32);
});

it('declines for free and lets the camper ask again later', function () {
    $request = TripRequestFactory::new()->create();
    $organiserId = $request->trip->organiser_id;

    $this->withToken(tokenFor(User::find($organiserId)))->postJson("/api/v1/requests/{$request->id}/decline")->assertOk();

    expect($request->fresh()->status)->toBe('declined');
    expect(record($organiserId)['cancelledCampers'])->toBe(0);
    $this->withToken(tokenFor(User::find($request->leader_id)))->postJson("/api/v1/trips/{$request->trip_id}/requests")->assertCreated();
});

it('counts an organiser cancelling an accepted or joined camper', function (string $state) {
    $request = TripRequestFactory::new()->{$state}()->create();
    $organiserId = $request->trip->organiser_id;

    $this->withToken(tokenFor(User::find($organiserId)))->postJson("/api/v1/requests/{$request->id}/cancel")->assertOk();

    expect($request->fresh())->status->toBe('cancelled')->ended_by->toBe('organiser');
    expect(record($organiserId)['cancelledCampers'])->toBe(1);
})->with(['accepted', 'joined']);

it('cannot cancel a pending request (that is a decline)', function () {
    $request = TripRequestFactory::new()->create();

    $this->withToken(tokenFor(User::find($request->trip->organiser_id)))->postJson("/api/v1/requests/{$request->id}/cancel")
        ->assertStatus(409)->assertJsonPath('code', 'trip_not_open');
});

it('lets the camper withdraw at any stage without penalty, freeing a paid seat', function () {
    $request = TripRequestFactory::new()->joined()->create();
    $trip = $request->trip;
    $camper = User::find($request->leader_id);

    $this->withToken(tokenFor($camper))->postJson("/api/v1/requests/{$request->id}/withdraw")
        ->assertOk()->assertJsonPath('seatsTaken', 0);

    expect($request->fresh())->status->toBe('withdrawn')->withdrawn_from->toBe('joined')->ended_by->toBe('camper');
    expect(record($trip->organiser_id)['cancelledCampers'])->toBe(0);

    $this->withToken(tokenFor($camper))->postJson("/api/v1/trips/{$trip->id}/requests")
        ->assertCreated()->assertJsonPath('myRequest.status', 'pending');
});

it('cancels a trip: one cancelled trip, one cancelled camper per accepted or joined request, pending ones declined', function () {
    $trip = TripFactory::new()->create();
    $pending = TripRequestFactory::new()->forTrip($trip)->create();
    TripRequestFactory::new()->accepted()->forTrip($trip)->create();
    TripRequestFactory::new()->joined()->forTrip($trip)->count(2)->create();

    $this->withToken(tokenFor(User::find($trip->organiser_id)))->postJson("/api/v1/trips/{$trip->id}/cancel")
        ->assertOk()->assertJsonPath('status', 'cancelled');

    expect(record($trip->organiser_id))->cancelledTrips->toBe(1)->cancelledCampers->toBe(3);
    expect($pending->fresh()->status)->toBe('declined');
    expect(TripRequest::query()->where('trip_id', $trip->id)->where('status', 'cancelled')->count())->toBe(3);
});

it('lets only the organiser cancel a trip, once', function () {
    $trip = TripFactory::new()->create();

    $this->withToken(tokenFor(tripReady()))->postJson("/api/v1/trips/{$trip->id}/cancel")->assertStatus(403);
    $this->withToken(tokenFor(User::find($trip->organiser_id)))->postJson("/api/v1/trips/{$trip->id}/cancel")->assertOk();
    $this->withToken(tokenFor(User::find($trip->organiser_id)))->postJson("/api/v1/trips/{$trip->id}/cancel")
        ->assertStatus(409)->assertJsonPath('code', 'trip_not_open');
});
