<?php

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;

it('makes leader and member friends when an invitation is accepted', function () {
    $leader = User::factory()->create();
    $amine = User::factory()->create();
    $party = makeParty($this, $leader);

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $amine->id]);
    partyAs($this, $amine)->postJson("/api/v1/parties/{$party->id}/accept")
        ->assertOk()->assertJsonPath('party.id', $party->id);

    $rows = DB::table('friendships')->get();
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('reason')->map(fn ($r) => json_decode($r, true))->unique()->all())->toBe([['kind' => 'party']]);
    expect(memberRow($party, $amine))->state->toBe('in');
});

it('makes no friends when an invitation is declined', function () {
    $leader = User::factory()->create();
    $amine = User::factory()->create();
    $party = makeParty($this, $leader);

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $amine->id]);
    partyAs($this, $amine)->postJson("/api/v1/parties/{$party->id}/decline")->assertOk()->assertJsonPath('party', null);

    expect(DB::table('friendships')->count())->toBe(0);
    expect(memberRow($party, $amine))->state->toBe('declined');
});

it('only answers an invitation you have', function () {
    $leader = User::factory()->create();
    $stranger = User::factory()->create();
    $party = makeParty($this, $leader);

    partyAs($this, $stranger)->postJson("/api/v1/parties/{$party->id}/accept")->assertNotFound();
});

it('leaves a forming party you are in when you accept another', function () {
    $leaderA = User::factory()->create();
    $leaderB = User::factory()->create();
    $amine = User::factory()->create();
    $a = makeParty($this, $leaderA);
    $b = makeParty($this, $leaderB);
    joinParty($this, $a, $amine);
    partyAs($this, $leaderB)->postJson("/api/v1/parties/{$b->id}/invites", ['userId' => $amine->id]);

    partyAs($this, $amine)->postJson("/api/v1/parties/{$b->id}/accept")->assertOk()->assertJsonPath('party.id', $b->id);

    expect(memberRow($a, $amine))->state->toBe('left');
    expect(memberRow($b, $amine))->state->toBe('in');
});

it('hands over or ends the forming party you lead when you accept another', function () {
    $leaderB = User::factory()->create();
    $amine = User::factory()->create();
    $sami = User::factory()->create();
    $own = makeParty($this, $amine);
    $b = makeParty($this, $leaderB);
    partyAs($this, $leaderB)->postJson("/api/v1/parties/{$b->id}/invites", ['userId' => $amine->id]);

    partyAs($this, $amine)->postJson("/api/v1/parties/{$b->id}/accept")->assertOk();
    expect($own->fresh()->state)->toBe('over');

    $own2 = makeParty($this, $sami);
    $other = User::factory()->create();
    $third = User::factory()->create();
    joinParty($this, $own2, $other);
    joinParty($this, $own2, $third);
    $c = makeParty($this, User::factory()->create());
    partyAs($this, User::find($c->leader_id))->postJson("/api/v1/parties/{$c->id}/invites", ['userId' => $sami->id]);

    partyAs($this, $sami)->postJson("/api/v1/parties/{$c->id}/accept")->assertOk();
    expect($own2->fresh()->leader_id)->toBe($other->id);
    expect(memberRow($own2, $other))->toBeNull();
    expect(memberRow($own2, $third))->state->toBe('in');
});

it('refuses to accept while your party is asking', function () {
    $leaderB = User::factory()->create();
    $amine = User::factory()->create();
    $own = makeParty($this, $amine);
    $b = makeParty($this, $leaderB);
    partyAs($this, $leaderB)->postJson("/api/v1/parties/{$b->id}/invites", ['userId' => $amine->id]);
    $own->update(['state' => 'asking']);

    partyAs($this, $amine)->postJson("/api/v1/parties/{$b->id}/accept")->assertStatus(409)->assertJsonPath('code', 'already_in_party');
    expect(memberRow($b, $amine))->state->toBe('invited');
});

it('refuses to accept into a party that already asked', function () {
    $leader = User::factory()->create();
    $amine = User::factory()->create();
    $party = makeParty($this, $leader);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $amine->id]);
    $party->update(['state' => 'asking']);

    partyAs($this, $amine)->postJson("/api/v1/parties/{$party->id}/accept")->assertStatus(409)->assertJsonPath('code', 'party_locked');
});

it('lists invitations with who has joined, by first name and photo, and hides the rest', function () {
    $leader = User::factory()->create(['first_name' => 'Hamza']);
    $sami = User::factory()->create(['first_name' => 'Sami']);
    $amine = User::factory()->create(['first_name' => 'Amine']);
    $nour = User::factory()->create(['first_name' => 'Nour']);
    $party = makeParty($this, $leader);
    joinParty($this, $party, $sami);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $amine->id]);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $nour->id]);

    $res = partyAs($this, $nour)->getJson('/api/v1/me/party')->assertOk()->assertJsonPath('party', null);
    $invite = $res->json('invitations.0');
    expect($invite['partyId'])->toBe($party->id)
        ->and($invite['leader']['firstName'])->toBe('Hamza')
        ->and(collect($invite['joined'])->pluck('firstName')->all())->toBe(['Sami'])
        ->and($invite['invitedCount'])->toBe(2);
    expect($res->getContent())->not->toContain('Amine');
});

it('lets two members in each other\'s invited party accept crosswise', function () {
    $leaderA = User::factory()->create();
    $leaderB = User::factory()->create();
    $amine = User::factory()->create();
    $nour = User::factory()->create();
    $a = makeParty($this, $leaderA);
    $b = makeParty($this, $leaderB);
    joinParty($this, $a, $amine);
    joinParty($this, $b, $nour);
    partyAs($this, $leaderB)->postJson("/api/v1/parties/{$b->id}/invites", ['userId' => $amine->id]);
    partyAs($this, $leaderA)->postJson("/api/v1/parties/{$a->id}/invites", ['userId' => $nour->id]);

    partyAs($this, $amine)->postJson("/api/v1/parties/{$b->id}/accept")->assertOk();
    partyAs($this, $nour)->postJson("/api/v1/parties/{$a->id}/accept")->assertOk();

    expect(memberRow($b, $amine))->state->toBe('in');
    expect(memberRow($a, $nour))->state->toBe('in');
});
