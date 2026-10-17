<?php

use Illuminate\Support\Facades\Event;
use Modules\Identity\Models\User;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Events\PartyChanged;

function invitedPair($test): array
{
    $leader = User::factory()->create();
    $amine = User::factory()->create();
    $party = makeParty($test, $leader);
    partyAs($test, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $amine->id])->assertOk();

    return [$leader, $amine, $party, memberRow($party, $amine)];
}

it('reminds an invited member once per 12 hours', function () {
    [$leader, $amine, $party, $row] = invitedPair($this);
    Event::fake([PartyChanged::class]);

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$row->id}/remind")->assertOk();
    Event::assertDispatched(PartyChanged::class, fn ($e) => $e->change === 'reminded' && $e->userId === $amine->id && $e->byId === $leader->id);
    expect($row->fresh()->reminded_at)->not->toBeNull();

    $this->travel(11)->hours();
    $res = partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$row->id}/remind")
        ->assertStatus(429)->assertJsonPath('code', 'rate_limited');
    expect($res->json('retryAfter'))->toBeGreaterThan(0)->toBeLessThanOrEqual(3600);

    $this->travel(2)->hours();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$row->id}/remind")->assertOk();
});

it('reminds a member who is in but not trip-ready, under the same 12 hour rule', function () {
    [$leader, $amine, $party, $row] = invitedPair($this);
    partyAs($this, $amine)->postJson("/api/v1/parties/{$party->id}/accept")->assertOk();
    Event::fake([PartyChanged::class]);

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$row->id}/remind")->assertOk();
    Event::assertDispatched(PartyChanged::class, fn ($e) => $e->change === 'reminded_ready' && $e->userId === $amine->id && $e->byId === $leader->id);

    $this->travel(11)->hours();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$row->id}/remind")
        ->assertStatus(429)->assertJsonPath('code', 'rate_limited')->assertJsonPath('retryAfter', fn ($v) => $v > 0 && $v <= 3600);

    $this->travel(2)->hours();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$row->id}/remind")->assertOk();
});

it('lets only the leader remind, and only invited or in people', function () {
    [$leader, $amine, $party, $row] = invitedPair($this);
    partyAs($this, $amine)->postJson("/api/v1/parties/{$party->id}/members/{$row->id}/remind")->assertForbidden()->assertJsonPath('code', 'not_leader');

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['contact' => '+21698765432'])->assertOk();
    $placeholder = PartyMember::query()->whereNull('user_id')->first();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$placeholder->id}/remind")->assertNotFound();

    partyAs($this, $amine)->postJson("/api/v1/parties/{$party->id}/decline")->assertOk();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$row->id}/remind")->assertNotFound();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$leader->id}/remind")->assertNotFound();
});

it('removes a member and tells them', function () {
    $fake = fakeTripRequests();
    [$leader, $amine, $party, $row] = invitedPair($this);
    partyAs($this, $amine)->postJson("/api/v1/parties/{$party->id}/accept")->assertOk();
    $party->forceFill(['state' => 'asking', 'request_id' => '01k0000000000000000000req1'])->save();
    Event::fake([PartyChanged::class]);

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$row->id}/remove")
        ->assertOk()->assertJsonPath('members', []);

    expect($row->fresh()->state)->toBe('removed');
    Event::assertDispatched(PartyChanged::class, fn ($e) => $e->change === 'removed' && $e->userId === $amine->id && $e->byId === $leader->id && $e->memberIds === [$leader->id]);
    expect($fake->calls)->toBe([['removePartyMember', ['01k0000000000000000000req1', $amine->id, $leader->id]]]);
    partyAs($this, $amine)->getJson('/api/v1/me/party')->assertJsonPath('party', null);
});

it('removes an invited person or a placeholder, leader only', function () {
    [$leader, $amine, $party, $row] = invitedPair($this);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['contact' => '+21698765432'])->assertOk();
    $placeholder = PartyMember::query()->whereNull('user_id')->first();

    partyAs($this, $amine)->postJson("/api/v1/parties/{$party->id}/members/{$row->id}/remove")->assertForbidden();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$placeholder->id}/remove")->assertOk();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$row->id}/remove")->assertOk();

    expect(PartyMember::query()->where('party_id', $party->id)->whereNull('user_id')->count())->toBe(0);
    expect($row->fresh()->state)->toBe('removed');
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/members/{$row->id}/remove")->assertNotFound();
});
