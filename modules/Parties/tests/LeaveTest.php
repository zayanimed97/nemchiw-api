<?php

use Illuminate\Support\Facades\Event;
use Modules\Identity\Models\User;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Events\PartyChanged;

function leave($test, Party $party, User $who)
{
    return partyAs($test, $who)->postJson("/api/v1/parties/{$party->id}/leave");
}

it('lets a member leave', function () {
    $leader = User::factory()->create();
    $amine = User::factory()->create();
    $party = makeParty($this, $leader);
    joinParty($this, $party, $amine);
    Event::fake([PartyChanged::class]);

    leave($this, $party, $amine)->assertOk()->assertJsonPath('party', null);

    expect(memberRow($party, $amine)->state)->toBe('left');
    Event::assertDispatched(PartyChanged::class, fn ($e) => $e->change === 'left' && $e->userId === $amine->id && $e->memberIds === [$leader->id]);
    leave($this, $party, $amine)->assertNotFound();
});

it('hands the lead to the earliest acceptance when the leader leaves', function () {
    $leader = User::factory()->create();
    $momen = User::factory()->create();
    $anas = User::factory()->create();
    $party = makeParty($this, $leader);
    joinPartyAt($this, $party, $momen, '2026-10-16 10:00:05');
    joinPartyAt($this, $party, $anas, '2026-10-16 10:00:01');
    Event::fake([PartyChanged::class]);

    leave($this, $party, $leader)->assertOk()->assertJsonPath('party', null);

    expect($party->fresh()->leader_id)->toBe($anas->id)->and($party->fresh()->state)->toBe('forming');
    expect(memberRow($party, $anas))->toBeNull(); // the leader has no member row
    expect(memberRow($party, $momen)->state)->toBe('in');
    Event::assertDispatched(PartyChanged::class, fn ($e) => $e->change === 'leader_changed' && $e->userId === $anas->id && $e->leaderId === $anas->id);
    partyAs($this, $anas)->getJson('/api/v1/me/party')->assertJsonPath('party.leaderId', $anas->id);
});

it('ends the party when the leader leaves with nobody in, and forgets the placeholders', function () {
    $leader = User::factory()->create();
    $amine = User::factory()->create();
    $party = makeParty($this, $leader);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $amine->id])->assertOk();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['contact' => '+21698765432'])->assertOk();
    Event::fake([PartyChanged::class]);

    leave($this, $party, $leader)->assertOk();

    expect($party->fresh()->state)->toBe('over');
    expect(PartyMember::query()->where('party_id', $party->id)->whereNull('user_id')->count())->toBe(0);
    Event::assertDispatched(PartyChanged::class, fn ($e) => $e->change === 'ended');
    partyAs($this, $leader)->postJson('/api/v1/parties')->assertCreated();
});

it('leaves a valid party when the leader and the earliest member leave together', function () {
    foreach ([['leader', 'anas'], ['anas', 'leader']] as $order) {
        $leader = User::factory()->create();
        $anas = User::factory()->create();
        $momen = User::factory()->create();
        $party = makeParty($this, $leader);
        joinPartyAt($this, $party, $anas, '2026-10-16 10:00:01');
        joinPartyAt($this, $party, $momen, '2026-10-16 10:00:05');
        $who = ['leader' => $leader, 'anas' => $anas];

        foreach ($order as $name) {
            leave($this, $party, $who[$name])->assertOk();
        }

        $fresh = $party->fresh();
        expect($fresh->state)->toBe('forming')->and($fresh->leader_id)->toBe($momen->id);
        expect(memberRow($fresh, $momen))->toBeNull();
    }
});

it('ends the party when the leader and the only member both leave', function () {
    $leader = User::factory()->create();
    $anas = User::factory()->create();
    $party = makeParty($this, $leader);
    joinParty($this, $party, $anas);

    leave($this, $party, $anas)->assertOk();
    leave($this, $party, $leader)->assertOk();

    expect($party->fresh()->state)->toBe('over');
});

it('lets only people in the party leave it', function () {
    $leader = User::factory()->create();
    $party = makeParty($this, $leader);
    $stranger = User::factory()->create();
    $invited = User::factory()->create();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $invited->id])->assertOk();

    leave($this, $party, $stranger)->assertNotFound();
    leave($this, $party, $invited)->assertNotFound();
});

it('takes a leaving member off the open request', function () {
    $fake = fakeTripRequests();
    $leader = User::factory()->create();
    $amine = User::factory()->create();
    $party = makeParty($this, $leader);
    joinParty($this, $party, $amine);
    $party->forceFill(['state' => 'asking', 'request_id' => '01k0000000000000000000req1'])->save();

    leave($this, $party, $amine)->assertOk();

    expect($fake->calls)->toBe([['removePartyMember', ['01k0000000000000000000req1', $amine->id, $amine->id]]]);
    expect($party->fresh()->state)->toBe('asking');
});

it('moves the request to the new leader when the leader leaves', function () {
    $fake = fakeTripRequests();
    $leader = User::factory()->create();
    $amine = User::factory()->create();
    $party = makeParty($this, $leader);
    joinParty($this, $party, $amine);
    $party->forceFill(['state' => 'going', 'request_id' => '01k0000000000000000000req1'])->save();

    leave($this, $party, $leader)->assertOk();

    expect($fake->calls)->toBe([
        ['changeLeader', ['01k0000000000000000000req1', $amine->id]],
        ['removePartyMember', ['01k0000000000000000000req1', $leader->id, $leader->id]],
    ]);
});

it('withdraws the request when the last person leaves', function () {
    $fake = fakeTripRequests();
    $leader = User::factory()->create();
    $party = makeParty($this, $leader);
    $party->forceFill(['state' => 'asking', 'request_id' => '01k0000000000000000000req1'])->save();

    leave($this, $party, $leader)->assertOk();

    expect($fake->calls)->toBe([['withdrawForParty', ['01k0000000000000000000req1']]]);
    expect($party->fresh()->state)->toBe('over');
});

it('leaves the party when the account is deleted', function () {
    $leader = User::factory()->create();
    $anas = User::factory()->create();
    $party = makeParty($this, $leader);
    joinParty($this, $party, $anas);

    partyAs($this, $leader)->postJson('/api/v1/me/delete')->assertNoContent();

    expect($party->fresh()->leader_id)->toBe($anas->id)->and($party->fresh()->state)->toBe('forming');
});
