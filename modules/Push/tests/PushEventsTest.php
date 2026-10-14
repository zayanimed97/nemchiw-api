<?php

use Illuminate\Support\Facades\Http;
use Modules\Identity\Models\User;
use Modules\Organisers\Actions\DecideApplication;
use Modules\Organisers\Models\OrganiserApplication;
use Modules\Push\Models\PushToken;
use Modules\Trips\Actions\Tick;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(function () {
    $this->travelTo('2026-10-10 10:00:00');
    $this->withoutDefer();
    Http::fake(['exp.host/*' => Http::response(['data' => []])]);
});

function deviceFor($user, string $token, string $locale = 'en'): void
{
    PushToken::query()->create(['user_id' => $user->id, 'token' => $token, 'platform' => 'android', 'locale' => $locale, 'last_seen_at' => now()]);
}

function sentTo(string $token): array
{
    return Http::recorded()->flatMap(fn ($pair) => $pair[0]->data())->where('to', $token)->values()->all();
}

it('tells the organiser about a new request, and the camper about the answer', function () {
    $trip = TripFactory::new()->create(['title' => 'Cork oaks']);
    $organiser = User::find($trip->organiser_id);
    $camper = tripReady(['first_name' => 'Amira']);
    deviceFor($organiser, 'ExponentPushToken[org]');
    deviceFor($camper, 'ExponentPushToken[cmp]');

    $this->withToken(tokenFor($camper))->postJson("/api/v1/trips/{$trip->id}/requests", ['note' => 'Salam'])->assertCreated();
    $request = TripRequest::query()->where('leader_id', $camper->id)->firstOrFail();
    $this->withToken(tokenFor($organiser))->postJson("/api/v1/requests/{$request->id}/accept")->assertOk();

    $org = sentTo('ExponentPushToken[org]');
    expect($org)->toHaveCount(1); // the note does not push twice
    expect([$org[0]['title'], $org[0]['body'], $org[0]['data']['type']])->toBe(['New request · Cork oaks', 'Amira wants to join', 'message']);
    $cmp = sentTo('ExponentPushToken[cmp]');
    expect([$cmp[0]['title'], $cmp[0]['body']])->toBe(["You're accepted · Cork oaks", 'Pay Momen and scan the code']);
});

it('reminds the day before: meetup for the paid, payment for the unsure', function () {
    $trip = TripFactory::new()->create(['title' => 'Cork oaks', 'starts_at' => '2026-10-11 09:00', 'ends_at' => '2026-10-12 09:00', 'meetup_at' => '2026-10-11 07:30']);
    $joined = TripRequestFactory::new()->forTrip($trip)->joined()->create();
    $unsure = TripRequestFactory::new()->forTrip($trip)->accepted()->create();
    deviceFor(User::find($trip->organiser_id), 'ExponentPushToken[org]');
    deviceFor(User::find($joined->leader_id), 'ExponentPushToken[joined]');
    deviceFor(User::find($unsure->leader_id), 'ExponentPushToken[unsure]');

    app(Tick::class)();

    // Tunis is UTC+1: 07:30 UTC is 08:30 there.
    expect(sentTo('ExponentPushToken[org]')[0]['body'])->toBe('Meetup at 08:30');
    expect(sentTo('ExponentPushToken[joined]')[0])->toMatchArray(['title' => 'Tomorrow · Cork oaks', 'body' => 'Meetup at 08:30', 'data' => ['type' => 'trip', 'tripId' => $trip->id]]);
    expect(sentTo('ExponentPushToken[unsure]')[0]['body'])->toBe('Pay and scan the code before you go');
});

it('tells applicants the decision', function () {
    $user = tripReady();
    deviceFor($user, 'ExponentPushToken[app]', 'fr');
    $application = OrganiserApplication::query()->create([
        'user_id' => $user->id, 'name' => 'Rando Nord', 'regions' => ['jendouba'], 'experience_years' => 6,
        'public_phone' => '+21671000000', 'status' => 'submitted', 'submitted_at' => now(),
    ]);

    app(DecideApplication::class)->approve($application);

    expect(sentTo('ExponentPushToken[app]')[0])->toMatchArray([
        'title' => 'Votre demande d’organisateur est acceptée', 'data' => ['type' => 'organiser'],
    ]);
});
