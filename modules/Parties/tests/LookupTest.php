<?php

use Modules\Identity\Models\User;

it('looks up by phone', function () {
    $leader = User::factory()->create();
    $amine = User::factory()->withPhone('+21622345678')->create(['first_name' => 'Amine', 'gender' => 'male']);
    $party = makeParty($this, $leader);

    $a = partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/lookup", ['contact' => '+216 22 345 678'])
        ->assertOk()->assertJsonPath('person.id', $amine->id)->assertJsonPath('person.gender', 'male')->assertJsonPath('blocked', null);
    $b = partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/lookup", ['contact' => '22345678'])->assertOk();

    expect($b->json('person'))->toBe($a->json('person'));
    $a->assertJsonMissing(['phone' => '+21622345678']);
    expect($a->getContent())->not->toContain('+216')->and($a->getContent())->not->toContain('22345678');
});

it('finds only verified phones and confirmed emails', function () {
    $leader = User::factory()->create();
    User::factory()->create(['phone' => '+21622345678']); // not verified
    User::factory()->create(['email' => 'amine@example.com']); // not provider-confirmed
    User::factory()->create(['email' => 'sami@example.com', 'confirmed_email' => 'sami@example.com']);
    $party = makeParty($this, $leader);

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/lookup", ['contact' => '22345678'])->assertNotFound()->assertJsonPath('code', 'not_found');
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/lookup", ['contact' => 'amine@example.com'])->assertNotFound();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/lookup", ['contact' => 'SAMI@Example.com'])->assertOk();
});

it('says when the person is you or already in the party', function () {
    $leader = User::factory()->withPhone('+21622000001')->create();
    $amine = User::factory()->withPhone('+21622345678')->create();
    $party = makeParty($this, $leader);

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/lookup", ['contact' => '22000001'])->assertJsonPath('blocked', 'self');
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/lookup", ['contact' => '22345678'])->assertJsonPath('blocked', null);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $amine->id]);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/lookup", ['contact' => '22345678'])->assertJsonPath('blocked', 'member');
});

it('lets only the leader look people up', function () {
    $leader = User::factory()->create();
    $other = User::factory()->create();
    $party = makeParty($this, $leader);

    partyAs($this, $other)->postJson("/api/v1/parties/{$party->id}/lookup", ['contact' => '22345678'])->assertForbidden()->assertJsonPath('code', 'not_leader');
});

it('rate-limits lookups', function () {
    $leader = User::factory()->create();
    $party = makeParty($this, $leader);

    foreach (range(1, 20) as $i) {
        partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/lookup", ['contact' => '22345678'])->assertNotFound();
    }

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/lookup", ['contact' => '22345678'])
        ->assertStatus(429)->assertJsonPath('code', 'lookup_limited')->assertJsonStructure(['retryAfter']);
});
