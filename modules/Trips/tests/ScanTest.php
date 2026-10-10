<?php

use Modules\Identity\Models\User;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Support\JoinCode;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

function codeFor(TripRequest $request, int $offsetWindows = 0): string
{
    return JoinCode::at(base64_decode($request->fresh()->code_secret), JoinCode::counter(now()->getTimestamp()) + $offsetWindows);
}

function scan($test, TripRequest $request, string $code, ?User $as = null)
{
    return $test->withToken(tokenFor($as ?? User::find($request->leader_id)))
        ->postJson("/api/v1/requests/{$request->id}/scan", ['code' => $code]);
}

it('joins the trip with the organiser\'s current code', function () {
    $request = TripRequestFactory::new()->accepted()->create();

    scan($this, $request, codeFor($request))
        ->assertOk()
        ->assertJsonPath('myRequest.status', 'joined')
        ->assertJsonPath('seatsTaken', 1);

    $request->refresh();
    expect($request->joined_at)->not->toBeNull()->and($request->code_secret)->toBeNull();
    expect($request->members()->sole()->scanned_at)->not->toBeNull();
});

it('accepts a code from the previous window (slow hands, slightly slow clock)', function () {
    $request = TripRequestFactory::new()->accepted()->create();

    scan($this, $request, codeFor($request, -1))->assertOk();
});

it('says a code from a few minutes ago has expired, and a wrong one is wrong', function () {
    $request = TripRequestFactory::new()->accepted()->create();

    scan($this, $request, codeFor($request, -6))->assertStatus(422)->assertJsonPath('code', 'code_expired');
    $wrong = codeFor($request) === '000001' ? '000002' : '000001';
    scan($this, $request, $wrong)->assertStatus(422)->assertJsonPath('code', 'invalid_code');
});

it('only takes codes made for this request', function () {
    $trip = TripFactory::new()->create();
    $mine = TripRequestFactory::new()->accepted()->forTrip($trip)->create();
    $theirs = TripRequestFactory::new()->accepted()->forTrip($trip)->create();

    scan($this, $mine, codeFor($theirs))->assertStatus(422)->assertJsonPath('code', 'invalid_code');
});

it('refuses someone who is not on the request', function () {
    $request = TripRequestFactory::new()->accepted()->create();

    scan($this, $request, codeFor($request), tripReady())->assertStatus(403)->assertJsonPath('code', 'forbidden');
});

it('refuses a request that is not accepted any more (stale screen after a cancellation)', function () {
    $request = TripRequestFactory::new()->accepted()->create();
    $code = codeFor($request);
    $this->withToken(tokenFor(User::find($request->trip->organiser_id)))->postJson("/api/v1/trips/{$request->trip_id}/cancel");

    scan($this, $request, $code)->assertStatus(409)->assertJsonPath('code', 'not_accepted');
});

it('refuses a second scan of the same request', function () {
    $request = TripRequestFactory::new()->accepted()->create();
    $code = codeFor($request);
    scan($this, $request, $code)->assertOk();

    scan($this, $request, $code)->assertStatus(409)->assertJsonPath('code', 'not_accepted');
});

it('refuses scans once the trip has started', function () {
    $trip = TripFactory::new()->create(['starts_at' => '2026-10-10 12:00', 'ends_at' => '2026-10-11 12:00', 'meetup_at' => '2026-10-10 11:00']);
    $request = TripRequestFactory::new()->accepted()->forTrip($trip)->create();
    $this->travelTo('2026-10-10 12:01:00');

    scan($this, $request, codeFor($request))->assertStatus(409)->assertJsonPath('code', 'not_accepted');
});

it('gives the last seat to the first scan and says the trip is full to the second', function () {
    $trip = TripFactory::new()->create(['seats' => 2]);
    TripRequestFactory::new()->joined()->forTrip($trip)->create();
    $first = TripRequestFactory::new()->accepted()->forTrip($trip)->create();
    $second = TripRequestFactory::new()->accepted()->forTrip($trip)->create();

    scan($this, $first, codeFor($first))->assertOk()->assertJsonPath('seatsTaken', 2);
    scan($this, $second, codeFor($second))->assertStatus(409)->assertJsonPath('code', 'trip_full');
    expect($second->fresh()->status)->toBe('accepted');
});

it('locks scanning for 10 minutes after 5 wrong codes', function () {
    $request = TripRequestFactory::new()->accepted()->create();
    $wrong = codeFor($request) === '000001' ? '000002' : '000001';
    foreach (range(1, 5) as $_) {
        scan($this, $request, $wrong)->assertStatus(422);
    }

    scan($this, $request, codeFor($request))->assertStatus(429)->assertJsonPath('code', 'rate_limited');

    $this->travel(601)->seconds();
    scan($this, $request, codeFor($request))->assertOk();
});

it('validates the code format', function () {
    $request = TripRequestFactory::new()->accepted()->create();

    scan($this, $request, '48291')->assertStatus(422)->assertJsonPath('code', 'validation');
});
