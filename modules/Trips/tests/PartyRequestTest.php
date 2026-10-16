<?php

use Illuminate\Support\Facades\Event;
use Modules\Identity\Models\User;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Parties\Models\Party;
use Modules\Shared\Events\RequestChanged;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Models\TripRequestMember;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

/** A leader and `$others` ready members in a forming party, and a trip. */
function readyPartyOf($test, int $others = 3, array $trip = []): array
{
    $leader = tripReady();
    $party = makeParty($test, $leader);
    $members = [];
    for ($i = 0; $i < $others; $i++) {
        $members[] = $m = tripReady();
        joinParty($test, $party, $m);
    }

    return [$leader, $party, $members, TripFactory::new()->create(array_merge(['price_tnd' => 50], $trip))];
}

function askAsParty($test, User $leader, Party $party, $trip)
{
    return partyAs($test, $leader)->postJson("/api/v1/trips/{$trip->id}/requests", ['partyId' => $party->id]);
}

function acceptAsOrganiser($test, TripRequest $request): void
{
    $test->withToken(tokenFor(User::find($request->trip->organiser_id)))->postJson("/api/v1/requests/{$request->id}/accept")->assertOk();
}

it('asks for a trip with the whole party', function () {
    [$leader, $party, $members, $trip] = readyPartyOf($this);

    askAsParty($this, $leader, $party, $trip)->assertCreated()->assertJsonPath('myRequest.status', 'pending');

    $request = TripRequest::query()->sole();
    expect($request->party_id)->toBe($party->id)->and($request->leader_id)->toBe($leader->id);
    expect(TripRequestMember::query()->where('request_id', $request->id)->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$leader, ...$members])->pluck('id')->sort()->values()->all());
    expect($party->fresh()->state)->toBe('asking')->and($party->fresh()->request_id)->toBe($request->id);
});

it('refuses with party_not_ready when someone isn\'t ready', function () {
    [$leader, $party, $members, $trip] = readyPartyOf($this, 2);
    $members[0]->forceFill(['emergency_contact' => null])->save();

    askAsParty($this, $leader, $party, $trip)->assertStatus(409)->assertJsonPath('code', 'party_not_ready')->assertJsonMissingPath('blockers');
    expect(TripRequest::query()->count())->toBe(0)->and($party->fresh()->state)->toBe('forming');
});

it('refuses with party_not_ready when someone hasn\'t answered', function () {
    [$leader, $party, , $trip] = readyPartyOf($this, 1);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => tripReady()->id])->assertOk();

    askAsParty($this, $leader, $party, $trip)->assertStatus(409)->assertJsonPath('code', 'party_not_ready');
});

it('refuses a party bigger than the free seats', function () {
    [$leader, $party, , $trip] = readyPartyOf($this, 3, ['seats' => 8]);
    foreach (range(1, 5) as $_) {
        TripRequestFactory::new()->forTrip($trip)->joined()->create();
    }

    askAsParty($this, $leader, $party, $trip)->assertStatus(409)->assertJsonPath('code', 'trip_full');
});

it('answers not_leader, then party_locked, before anything else', function () {
    [$leader, $party, $members, $trip] = readyPartyOf($this, 1);

    askAsParty($this, $members[0], $party, $trip)->assertStatus(403)->assertJsonPath('code', 'not_leader');
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    askAsParty($this, $leader, $party, $trip)->assertStatus(409)->assertJsonPath('code', 'party_locked');
});

it('a member leaving between accept and scan leaves the rest to be joined', function () {
    [$leader, $party, $members, $trip] = readyPartyOf($this);
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    $request = TripRequest::query()->sole();
    acceptAsOrganiser($this, $request);
    $leaver = $members[2];

    partyAs($this, $leaver)->postJson("/api/v1/parties/{$party->id}/leave")->assertOk();
    scan($this, $request, codeFor($request))->assertOk()->assertJsonPath('myRequest.status', 'joined')->assertJsonPath('seatsTaken', 3);

    $rows = TripRequestMember::query()->where('request_id', $request->id)->get();
    expect($rows)->toHaveCount(3)->and($rows->whereNull('scanned_at'))->toHaveCount(0)
        ->and($rows->contains('user_id', $leaver->id))->toBeFalse();
    expect($party->fresh()->state)->toBe('going');
});

it('the scan needs a seat for every member', function () {
    [$leader, $party, , $trip] = readyPartyOf($this, 2, ['seats' => 8]);
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    $request = TripRequest::query()->sole();
    acceptAsOrganiser($this, $request);
    foreach (range(1, 6) as $_) {
        TripRequestFactory::new()->forTrip($trip)->joined()->create();
    }

    scan($this, $request, codeFor($request))->assertStatus(409)->assertJsonPath('code', 'trip_full');
    expect($request->fresh()->status)->toBe('accepted')->and(TripRequestMember::query()->where('request_id', $request->id)->whereNotNull('scanned_at')->count())->toBe(0);
});

it('any member can scan', function () {
    [$leader, $party, $members, $trip] = readyPartyOf($this, 2);
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    $request = TripRequest::query()->sole();
    acceptAsOrganiser($this, $request);

    scan($this, $request, codeFor($request), $members[1])->assertOk()->assertJsonPath('myRequest.status', 'joined');

    expect($request->fresh()->status)->toBe('joined')->and($party->fresh()->state)->toBe('going');
});

it('members see the trip in my trips with role member, only the leader withdraws', function () {
    [$leader, $party, $members, $trip] = readyPartyOf($this, 2);
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    $request = TripRequest::query()->sole();

    $mine = partyAs($this, $members[0])->getJson('/api/v1/me/trips')->assertOk();
    expect($mine->json('going.0.myRequest'))->toMatchArray(['id' => $request->id, 'partyId' => $party->id, 'role' => 'member', 'members' => 3]);
    expect(partyAs($this, $leader)->getJson('/api/v1/me/trips')->json('going.0.myRequest'))->toMatchArray(['role' => 'leader', 'partyId' => $party->id, 'members' => 3]);

    partyAs($this, $members[0])->postJson("/api/v1/requests/{$request->id}/withdraw")->assertStatus(403)->assertJsonPath('code', 'forbidden');
    partyAs($this, $leader)->postJson("/api/v1/requests/{$request->id}/withdraw")->assertOk();
    expect($request->fresh()->status)->toBe('withdrawn')->and($party->fresh()->state)->toBe('forming')->and($party->fresh()->request_id)->toBeNull();
});

it('the organiser sees the party and the total', function () {
    [$leader, $party, , $trip] = readyPartyOf($this);
    askAsParty($this, $leader, $party, $trip)->assertCreated();

    $res = $this->withToken(tokenFor(User::find($trip->organiser_id)))->getJson('/api/v1/me/trips')->assertOk();
    $hosted = $res->json('hosting.0.requests.0');

    expect($hosted['totalTnd'])->toBe(200)->and($hosted['party']['size'])->toBe(4)->and($hosted['party']['members'])->toHaveCount(4);
    expect($hosted['party']['members'][0])->toHaveKey('firstName');
});

it('a solo request has no party and a total of one seat', function () {
    $request = TripRequestFactory::new()->forTrip($trip = TripFactory::new()->create(['price_tnd' => 50]))->create();

    $hosted = $this->withToken(tokenFor(User::find($trip->organiser_id)))->getJson('/api/v1/me/trips')->json('hosting.0.requests.0');

    expect($hosted['party'])->toBeNull()->and($hosted['totalTnd'])->toBe(50);
    expect($request->party_id)->toBeNull();
});

it('cancelling a joined party counts every member', function () {
    [$leader, $party, , $trip] = readyPartyOf($this);
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    $request = TripRequest::query()->sole();
    acceptAsOrganiser($this, $request);
    scan($this, $request, codeFor($request))->assertOk();

    $this->withToken(tokenFor(User::find($trip->organiser_id)))->postJson("/api/v1/requests/{$request->id}/cancel")->assertOk();

    expect(app(OrganiserRecords::class)->cards([$trip->organiser_id])[$trip->organiser_id]['record']['cancelledCampers'])->toBe(4);
    expect($party->fresh()->state)->toBe('forming');
});

it('cancelling the trip counts every member of an accepted party', function () {
    [$leader, $party, , $trip] = readyPartyOf($this);
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    acceptAsOrganiser($this, TripRequest::query()->sole());

    $this->withToken(tokenFor(User::find($trip->organiser_id)))->postJson("/api/v1/trips/{$trip->id}/cancel")->assertOk();

    expect(app(OrganiserRecords::class)->cards([$trip->organiser_id])[$trip->organiser_id]['record']['cancelledCampers'])->toBe(4);
});

it('a declined party request returns the party to forming', function () {
    [$leader, $party, , $trip] = readyPartyOf($this, 1);
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    $request = TripRequest::query()->sole();
    $party->forceFill(['last_activity_at' => now()->subDays(20)])->save();

    $this->withToken(tokenFor(User::find($trip->organiser_id)))->postJson("/api/v1/requests/{$request->id}/decline")->assertOk();

    $party->refresh();
    expect($party->state)->toBe('forming')->and($party->request_id)->toBeNull()->and($party->last_activity_at->isToday())->toBeTrue();
});

it('shows the request on the party', function () {
    [$leader, $party, , $trip] = readyPartyOf($this, 1);
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    $request = TripRequest::query()->sole();

    partyAs($this, $leader)->getJson('/api/v1/me/party')->assertOk()
        ->assertJsonPath('party.request', ['id' => $request->id, 'tripId' => $trip->id, 'status' => 'pending']);
});

it('puts the party in asking inside the request\'s transaction, before any event is handled', function () {
    [$leader, $party, , $trip] = readyPartyOf($this, 1);
    Event::fake([RequestChanged::class]);

    askAsParty($this, $leader, $party, $trip)->assertCreated();

    $request = TripRequest::query()->sole();
    expect($party->fresh()->state)->toBe('asking')->and($party->fresh()->request_id)->toBe($request->id);
    askAsParty($this, $leader, $party, TripFactory::new()->create())->assertStatus(409)->assertJsonPath('code', 'party_locked');
});
