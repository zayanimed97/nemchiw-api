<?php

use Illuminate\Support\Facades\Event;
use Modules\Identity\Models\User;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Events\ContactConfirmed;
use Modules\Shared\Events\PartyChanged;
use Modules\Shared\Support\ContactHash;

beforeEach(fn () => config(['parties.contact_key' => 'test-key']));

it('turns a placeholder into an invitation when its contact is confirmed', function () {
    $leader = User::factory()->create();
    $party = makeParty($this, $leader);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['contact' => '+21698765432'])->assertOk();
    $amine = User::factory()->create();
    Event::fake([PartyChanged::class]);

    event(new ContactConfirmed($amine->id, ContactHash::of('+21698765432')));
    event(new ContactConfirmed($amine->id, ContactHash::of('+21698765432'))); // confirmed again at the next sign-in

    $rows = PartyMember::query()->where('party_id', $party->id)->get();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->user_id)->toBe($amine->id)->and($rows[0]->contact_hash)->toBeNull()
        ->and($rows[0]->contact_masked)->toBeNull()->and($rows[0]->state)->toBe('invited');
    Event::assertDispatchedTimes(PartyChanged::class, 1);
    Event::assertDispatched(PartyChanged::class, fn ($e) => $e->change === 'invited' && $e->userId === $amine->id);
    partyAs($this, $amine)->getJson('/api/v1/me/party')->assertJsonPath('invitations.0.partyId', $party->id);
});

it('keeps an arrival after the request as an invitation that cannot be accepted', function () {
    $leader = User::factory()->create();
    $anas = User::factory()->create();
    $party = makeParty($this, $leader);
    joinParty($this, $party, $anas);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['contact' => '+21698765432'])->assertOk();
    $party->forceFill(['state' => 'asking'])->save();
    $amine = User::factory()->create();

    event(new ContactConfirmed($amine->id, ContactHash::of('+21698765432')));

    expect(memberRow($party, $amine)->state)->toBe('invited');
    partyAs($this, $amine)->postJson("/api/v1/parties/{$party->id}/accept")->assertStatus(409)->assertJsonPath('code', 'party_locked');
    expect(memberRow($party, $amine)->state)->toBe('invited');
});

it('drops a placeholder whose owner already is in the party', function () {
    $leader = User::factory()->create();
    $party = makeParty($this, $leader);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['contact' => '+21698765432'])->assertOk();

    event(new ContactConfirmed($leader->id, ContactHash::of('+21698765432')));

    expect(PartyMember::query()->where('party_id', $party->id)->count())->toBe(0);
});

it('ignores contacts nobody invited', function () {
    $amine = User::factory()->create();
    event(new ContactConfirmed($amine->id, ContactHash::of('+21698765432')));
    expect(PartyMember::query()->count())->toBe(0);
});
