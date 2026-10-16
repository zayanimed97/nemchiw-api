<?php

use Modules\Identity\Models\User;
use Modules\Parties\Contracts\PartyRoster;
use Modules\Parties\Models\PartyMember;
use Modules\Trips\Models\TripRequestMember;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;
use Tests\Support\FakeProfilePhotos;

function readinessUrl($party, $trip): string
{
    return "/api/v1/parties/{$party->id}/readiness?trip={$trip->id}";
}

/** A leader with a party and a trip they could ask for. */
function readyParty($test, array $trip = []): array
{
    $leader = tripReady(['first_name' => 'Leila']);
    $party = makeParty($test, $leader);

    return [$leader, $party, TripFactory::new()->create($trip)];
}

it('is ready when everyone is ready', function () {
    [$leader, $party, $trip] = readyParty($this, ['seats' => 5]);
    joinParty($this, $party, tripReady());
    TripRequestFactory::new()->forTrip($trip)->joined()->create();

    partyAs($this, $leader)->getJson(readinessUrl($party, $trip))->assertOk()
        ->assertExactJson(['ready' => true, 'size' => 2, 'freeSeats' => 4, 'blockers' => []]);
});

it('lists the leader first, then members in invitation order', function () {
    [$leader, $party, $trip] = readyParty($this);
    $leader->forceFill(['emergency_contact' => null])->save();
    $first = tripReady(['first_name' => 'Amine', 'emergency_contact' => null]);
    $second = User::factory()->create(['first_name' => 'Sonia']);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $second->id])->assertOk();
    joinParty($this, $party, $first);
    memberRow($party, $second)->forceFill(['invited_at' => now()->subDay()])->save();

    $res = partyAs($this, $leader)->getJson(readinessUrl($party, $trip))->assertOk();
    expect($res->json('ready'))->toBeFalse();
    expect(collect($res->json('blockers'))->pluck('userId')->all())->toBe([$leader->id, $second->id, $first->id]);
    expect($res->json('blockers.0'))->toMatchArray(['memberId' => null, 'name' => 'Leila', 'reason' => 'not_ready', 'missing' => ['emergencyContact']]);
});

it('reports not_ready with the missing items, and no_answer for the invited', function () {
    [$leader, $party, $trip] = readyParty($this);
    $thin = tripReady(['first_name' => 'Thin', 'emergency_contact' => null]);
    joinParty($this, $party, $thin);
    $invited = tripReady(['first_name' => 'Waiting']);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $invited->id])->assertOk();
    FakeProfilePhotos::$verified = array_values(array_diff(FakeProfilePhotos::$verified, [$thin->id]));

    $blockers = partyAs($this, $leader)->getJson(readinessUrl($party, $trip))->assertOk()->json('blockers');
    expect($blockers)->toHaveCount(2);
    expect($blockers[0])->toMatchArray(['memberId' => memberRow($party, $thin)->id, 'userId' => $thin->id, 'name' => 'Thin', 'reason' => 'not_ready', 'missing' => ['photo', 'emergencyContact']]);
    expect($blockers[1])->toMatchArray(['memberId' => memberRow($party, $invited)->id, 'userId' => $invited->id, 'reason' => 'no_answer', 'missing' => []]);
});

it('reports a placeholder as not_on_app with no name', function () {
    [$leader, $party, $trip] = readyParty($this);
    $row = PartyMember::query()->create(['party_id' => $party->id, 'user_id' => null, 'contact_hash' => str_repeat('a', 64), 'contact_masked' => '+216 ** *** 123', 'state' => 'invited', 'invited_at' => now()]);

    $res = partyAs($this, $leader)->getJson(readinessUrl($party, $trip))->assertOk();
    expect($res->json('blockers.0'))->toBe(['memberId' => $row->id, 'userId' => null, 'name' => null, 'reason' => 'not_on_app', 'missing' => []]);
    expect($res->json('size'))->toBe(2);
});

it('reports too_young, organiser, already_requested and women_only', function () {
    [$leader, $party, $trip] = readyParty($this, ['women_only' => true]);
    $leader->forceFill(['gender' => 'female'])->save();
    $kid = tripReady(['first_name' => 'Kid', 'gender' => 'female', 'birth_date' => now()->subYears(17)->toDateString()]);
    $host = User::query()->findOrFail($trip->organiser_id);
    $host->forceFill(['gender' => 'female'])->save();
    $asked = tripReady(['first_name' => 'Asked', 'gender' => 'female']);
    $man = tripReady(['first_name' => 'Man', 'gender' => 'male']);
    foreach ([$kid, $host, $asked, $man] as $user) {
        joinParty($this, $party, $user);
    }
    $other = TripRequestFactory::new()->forTrip($trip)->create();
    TripRequestMember::query()->create(['request_id' => $other->id, 'user_id' => $asked->id]);

    $reasons = collect(partyAs($this, $leader)->getJson(readinessUrl($party, $trip))->assertOk()->json('blockers'))->pluck('reason', 'userId')->all();
    expect($reasons)->toBe([$kid->id => 'too_young', $host->id => 'organiser', $asked->id => 'already_requested', $man->id => 'women_only']);
});

it('ignores declined, left and removed members', function () {
    [$leader, $party, $trip] = readyParty($this);
    foreach (['declined', 'left', 'removed'] as $state) {
        PartyMember::query()->create(['party_id' => $party->id, 'user_id' => User::factory()->create()->id, 'state' => $state, 'invited_at' => now()]);
    }

    partyAs($this, $leader)->getJson(readinessUrl($party, $trip))->assertOk()->assertJsonPath('ready', true)->assertJsonPath('size', 1);
});

it('is for the leader only and needs a known trip', function () {
    [$leader, $party, $trip] = readyParty($this);
    $member = tripReady();
    joinParty($this, $party, $member);

    partyAs($this, $member)->getJson(readinessUrl($party, $trip))->assertForbidden()->assertJsonPath('code', 'not_leader');
    partyAs($this, $leader)->getJson("/api/v1/parties/{$party->id}/readiness?trip=01jzzzzzzzzzzzzzzzzzzzzzzz")->assertNotFound()->assertJsonPath('code', 'not_found');
    partyAs($this, $leader)->getJson("/api/v1/parties/{$party->id}/readiness")->assertNotFound();
});

it('gives Trips the people and blockers for an ask', function () {
    [$leader, $party, $trip] = readyParty($this);
    $member = tripReady();
    joinParty($this, $party, $member);
    $invited = User::factory()->create();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $invited->id])->assertOk();

    $ask = app(PartyRoster::class)->forAsk($party->id, $leader->id, $trip->id);
    expect($ask['memberIds'])->toBe([$leader->id, $member->id]);
    expect(collect($ask['blockers'])->pluck('reason')->all())->toBe(['no_answer']);
});
