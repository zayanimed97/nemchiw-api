<?php

use Illuminate\Http\Request;
use Modules\Identity\Http\PublicProfileResource;
use Modules\Identity\Models\User;

it('stores medical info encrypted and returns it to the owner only', function () {
    $user = User::factory()->create();

    $this->withToken(tokenFor($user))
        ->postJson('/api/v1/me', ['medicalInfo' => ['note' => 'Asthma, carries an inhaler']])
        ->assertOk()
        ->assertJsonPath('medicalInfo.note', 'Asthma, carries an inhaler');

    $raw = DB::table('users')->where('id', $user->id)->value('medical_info');
    expect($raw)->not->toContain('Asthma');
    $public = PublicProfileResource::make($user->fresh())->resolve(Request::create('/'));
    expect(json_encode($public))->not->toContain('Asthma');
});

it('accepts "nothing to report" as a null note', function () {
    $user = User::factory()->create();

    $this->withToken(tokenFor($user))
        ->postJson('/api/v1/me', ['medicalInfo' => ['note' => null]])
        ->assertOk()
        ->assertJsonPath('medicalInfo', ['note' => null]);
});

it('rejects a medical note over 500 characters', function () {
    $user = User::factory()->create();

    $this->withToken(tokenFor($user))
        ->postJson('/api/v1/me', ['medicalInfo' => ['note' => str_repeat('a', 501)]])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation');
});
