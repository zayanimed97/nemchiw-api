<?php

use Illuminate\Support\Facades\Http;
use Modules\Identity\Models\User;
use Modules\Push\Models\PushToken;
use Modules\Shared\Events\PartyChanged;
use Modules\Trips\Actions\Tick;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Models\TripRequestMember;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(function () {
    $this->travelTo('2026-10-10 10:00:00');
    $this->withoutDefer();
    Http::fake(['exp.host/*' => Http::response(['data' => []])]);
});

function partyDevice(User $user): string
{
    $token = "ExponentPushToken[{$user->id}]";
    PushToken::query()->create(['user_id' => $user->id, 'token' => $token, 'platform' => 'android', 'locale' => 'en', 'last_seen_at' => now()]);

    return $token;
}

function partyPushes(User $user): array
{
    return Http::recorded()->flatMap(fn ($pair) => $pair[0]->data())->where('to', "ExponentPushToken[{$user->id}]")->values()->all();
}

/** @return array{User, User, User, User} leader Hamza, Amine, Sana, Rim, each with a phone. */
function partyFour(): array
{
    $people = array_map(fn ($name) => User::factory()->create(['first_name' => $name]), ['Hamza', 'Amine', 'Sana', 'Rim']);
    foreach ($people as $p) {
        partyDevice($p);
    }

    return $people;
}

function partyEvent(string $change, ?User $about, ?User $by, User $leader, array $members): void
{
    event(new PartyChanged('party1', null, $change, $about?->id, $by?->id, $leader->id, array_map(fn ($u) => $u->id, $members)));
}

it('tells the invitee who invited them, without assuming a pronoun', function () {
    [$hamza, $amine] = partyFour();

    partyEvent('invited', $amine, $hamza, $hamza, [$hamza]);

    expect(partyPushes($hamza))->toBe([]);
    expect(partyPushes($amine))->toHaveCount(1);
    expect(partyPushes($amine)[0])->toMatchArray(['title' => 'Hamza invited you to a party', 'data' => ['type' => 'party', 'partyId' => 'party1']]);
});

it('sends nothing for a placeholder invite', function () {
    [$hamza] = partyFour();
    partyEvent('invited', null, $hamza, $hamza, [$hamza]);
    Http::assertNothingSent();
});

it('tells the leader who accepted or declined', function () {
    [$hamza, $amine, $sana] = partyFour();

    partyEvent('accepted', $amine, $amine, $hamza, [$hamza, $amine]);
    partyEvent('declined', $sana, $sana, $hamza, [$hamza]);

    expect(array_column(partyPushes($hamza), 'title'))->toBe(['Amine joined your party', 'Sana declined the party invitation']);
    expect(partyPushes($amine))->toBe([]);
    expect(partyPushes($sana))->toBe([]);
    expect(partyPushes($hamza)[0]['data'])->toBe(['type' => 'party', 'partyId' => 'party1']);
});

it('reminds an invited member', function () {
    [$hamza, $amine] = partyFour();

    partyEvent('reminded', $amine, $hamza, $hamza, [$hamza]);

    expect(partyPushes($amine))->toHaveCount(1);
    expect(partyPushes($amine)[0]['title'])->toBe('Hamza is waiting for your answer to the party invitation');
    expect(partyPushes($hamza))->toBe([]);
});

it('tells a removed member', function () {
    [$hamza, $amine] = partyFour();

    partyEvent('removed', $amine, $hamza, $hamza, [$hamza]);

    expect(partyPushes($amine)[0])->toMatchArray(['title' => 'You are no longer in the party', 'data' => ['type' => 'party', 'partyId' => 'party1']]);
    expect(partyPushes($hamza))->toBe([]);
});

it('tells everyone remaining about a new leader, except the one who acted', function () {
    [$hamza, $amine, $sana] = partyFour();

    partyEvent('leader_changed', $amine, $hamza, $amine, [$amine, $sana]);

    expect(partyPushes($sana)[0]['title'])->toBe('Amine now leads the party');
    expect(partyPushes($amine)[0]['title'])->toBe('Amine now leads the party');
    expect(partyPushes($hamza))->toBe([]);
});

it('tells everyone when the party ends, except the one who ended it', function () {
    [$hamza, $amine, $sana] = partyFour();

    partyEvent('ended', null, $hamza, $hamza, [$hamza, $amine, $sana]);
    partyEvent('auto_ended', null, null, $hamza, [$hamza, $amine]);

    expect(array_column(partyPushes($amine), 'title'))->toBe(['The party has ended', 'The party has ended']);
    expect(array_column(partyPushes($hamza), 'title'))->toBe(['The party has ended']); // only the automatic one
    expect(array_column(partyPushes($sana), 'title'))->toBe(['The party has ended']);
});

it('sends nothing when someone leaves, and nothing for request-driven changes', function () {
    [$hamza, $amine] = partyFour();

    partyEvent('left', $amine, $amine, $hamza, [$hamza]);
    foreach (['request_asked', 'request_accepted', 'request_scanned', 'request_ended'] as $change) {
        partyEvent($change, null, null, $hamza, [$hamza, $amine]);
    }

    Http::assertNothingSent();
});

function partyPushRequest(array $members, string $state = 'pending', ?string $title = null): TripRequest
{
    $trip = TripFactory::new()->create(['title' => $title ?? 'Cork oaks', 'starts_at' => '2026-10-11 09:00', 'ends_at' => '2026-10-12 09:00', 'meetup_at' => '2026-10-11 07:30']);
    $factory = TripRequestFactory::new()->forTrip($trip)->state(['leader_id' => $members[0]->id]);
    $request = match ($state) {
        'accepted' => $factory->accepted()->create(),
        'joined' => $factory->joined()->create(),
        default => $factory->create(),
    };
    foreach (array_slice($members, 1) as $m) {
        TripRequestMember::query()->create(['request_id' => $request->id, 'user_id' => $m->id]);
    }

    return $request;
}

it('tells all four members when the organiser accepts a party request', function () {
    $four = partyFour();
    $request = partyPushRequest($four);
    $organiser = User::find($request->trip->organiser_id);

    $this->withToken(tokenFor($organiser))->postJson("/api/v1/requests/{$request->id}/accept")->assertOk();

    foreach ($four as $member) {
        $pushes = partyPushes($member);
        expect($pushes)->toHaveCount(1);
        expect($pushes[0]['title'])->toBe("You're accepted · Cork oaks");
    }
});

it('reminds every member of joined and accepted party requests the day before', function () {
    $joined = partyFour();
    $accepted = partyFour();
    $joinedRequest = partyPushRequest($joined, 'joined');
    $acceptedRequest = TripRequestFactory::new()->forTrip($joinedRequest->trip)->state(['leader_id' => $accepted[0]->id])->accepted()->create();
    foreach (array_slice($accepted, 1) as $m) {
        TripRequestMember::query()->create(['request_id' => $acceptedRequest->id, 'user_id' => $m->id]);
    }

    app(Tick::class)();

    foreach ($joined as $member) {
        expect(partyPushes($member)[0]['body'])->toBe('Meetup at 08:30');
    }
    foreach ($accepted as $member) {
        expect(partyPushes($member)[0]['body'])->toBe('Pay and scan the code before you go');
    }
});
