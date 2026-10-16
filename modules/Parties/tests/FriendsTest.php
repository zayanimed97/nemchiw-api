<?php

use Modules\Identity\Models\User;

it('lists friends and removes them silently, one direction only', function () {
    $leader = User::factory()->create(['first_name' => 'Hamza']);
    $amine = User::factory()->create(['first_name' => 'Amine']);
    $party = makeParty($this, $leader);
    joinParty($this, $party, $amine);

    partyAs($this, $leader)->getJson('/api/v1/me/friends')->assertOk()
        ->assertJsonPath('data.0.person.firstName', 'Amine')
        ->assertJsonPath('data.0.reason', ['kind' => 'party'])
        ->assertJsonStructure(['data' => [['person', 'reason', 'since']]]);

    partyAs($this, $leader)->postJson("/api/v1/me/friends/{$amine->id}/remove")->assertNoContent();

    partyAs($this, $leader)->getJson('/api/v1/me/friends')->assertJsonPath('data', []);
    partyAs($this, $amine)->getJson('/api/v1/me/friends')->assertJsonPath('data.0.person.firstName', 'Hamza');
});

it('needs a signed-in user', function () {
    $this->getJson('/api/v1/me/friends')->assertUnauthorized();
    $this->postJson('/api/v1/parties')->assertUnauthorized();
});
