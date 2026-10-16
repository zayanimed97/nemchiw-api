<?php

use Illuminate\Support\Facades\Event;
use Modules\Identity\Models\User;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Events\PartyChanged;
use Modules\Shared\Support\ContactHash;

it('creates a party led by the caller', function () {
    $leader = User::factory()->create(['first_name' => 'Hamza']);

    partyAs($this, $leader)->postJson('/api/v1/parties')
        ->assertCreated()
        ->assertJsonPath('state', 'forming')
        ->assertJsonPath('leaderId', $leader->id)
        ->assertJsonPath('members', []);

    partyAs($this, $leader)->postJson('/api/v1/parties')->assertStatus(409)->assertJsonPath('code', 'already_in_party');
    expect(Party::query()->count())->toBe(1);
});

it('lets a new party start once the old one is over', function () {
    $leader = User::factory()->create();
    makeParty($this, $leader)->update(['state' => 'over']);

    partyAs($this, $leader)->postJson('/api/v1/parties')->assertCreated();
});

it('invites a user by id', function () {
    Event::fake([PartyChanged::class]);
    $leader = User::factory()->create();
    $amine = User::factory()->create(['first_name' => 'Amine']);
    $party = makeParty($this, $leader);

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $amine->id])
        ->assertOk()
        ->assertJsonPath('members.0.userId', $amine->id)
        ->assertJsonPath('members.0.state', 'invited')
        ->assertJsonPath('members.0.person.firstName', 'Amine');

    Event::assertDispatched(PartyChanged::class, fn ($e) => $e->change === 'invited' && $e->userId === $amine->id
        && $e->byId === $leader->id && $e->memberIds === [$leader->id]);
});

it('keeps the 20-person limit, placeholders included', function () {
    $leader = User::factory()->create();
    $party = makeParty($this, $leader);
    foreach (range(1, 18) as $i) {
        PartyMember::query()->create(['party_id' => $party->id, 'user_id' => User::factory()->create()->id, 'state' => 'invited', 'invited_at' => now()]);
    }
    PartyMember::query()->create(['party_id' => $party->id, 'contact_hash' => str_repeat('a', 64), 'contact_masked' => '+216 98 ··· 432', 'state' => 'invited', 'invited_at' => now()]);

    $one = User::factory()->create();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $one->id])
        ->assertStatus(409)->assertJsonPath('code', 'party_full');
});

it('lets only the leader invite, and only while forming', function () {
    $leader = User::factory()->create();
    $other = User::factory()->create();
    $target = User::factory()->create();
    $party = makeParty($this, $leader);

    partyAs($this, $other)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $target->id])
        ->assertForbidden()->assertJsonPath('code', 'not_leader');

    $party->update(['state' => 'asking']);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $target->id])
        ->assertStatus(409)->assertJsonPath('code', 'party_locked');
});

it('invites a contact with no account as a placeholder', function () {
    $leader = User::factory()->create();
    $party = makeParty($this, $leader);

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['contact' => '98 765 432'])
        ->assertOk()
        ->assertJsonPath('members.0.userId', null)
        ->assertJsonPath('members.0.person', null)
        ->assertJsonPath('members.0.contact', '+216 98 ··· 432');

    $row = PartyMember::query()->firstOrFail();
    expect($row->user_id)->toBeNull()->and($row->contact_hash)->toBe(ContactHash::of('+21698765432'));

    // a member never sees the placeholder row
    $member = User::factory()->create();
    joinParty($this, $party, $member);
    $view = partyAs($this, $member)->getJson('/api/v1/me/party')->assertOk()->json('party');
    expect(collect($view['members'])->pluck('contact')->filter()->all())->toBe([]);
    expect(count($view['members']))->toBe(1);
});

it('turns a contact that has an account into a normal invitation', function () {
    $leader = User::factory()->create();
    $amine = User::factory()->withPhone('+21622345678')->create();
    $party = makeParty($this, $leader);

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['contact' => '+216 22 345 678'])
        ->assertOk()->assertJsonPath('members.0.userId', $amine->id);
    expect(PartyMember::query()->whereNotNull('contact_hash')->count())->toBe(0);
});

it('refuses an invitation to yourself or to nobody', function () {
    $leader = User::factory()->withPhone('+21622345678')->create();
    $party = makeParty($this, $leader);

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['contact' => '22345678'])->assertStatus(422);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", [])->assertStatus(422);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['contact' => 'nonsense'])->assertStatus(422);
});

it('re-invites someone who declined', function () {
    $leader = User::factory()->create();
    $amine = User::factory()->create();
    $party = makeParty($this, $leader);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $amine->id]);
    partyAs($this, $amine)->postJson("/api/v1/parties/{$party->id}/decline")->assertOk();

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $amine->id])->assertOk();

    expect(memberRow($party, $amine)->state)->toBe('invited');
    expect(PartyMember::query()->count())->toBe(1);
});
