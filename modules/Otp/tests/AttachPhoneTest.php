<?php

use Modules\Identity\Models\User;
use Modules\Otp\Contracts\OtpSender;
use Modules\Otp\Testing\FakeOtpSender;

beforeEach(function () {
    $this->codes = new FakeOtpSender;
    $this->app->instance(OtpSender::class, $this->codes);
    $this->user = User::factory()->create();
    $this->token = tokenFor($this->user);
});

it('requires a token', function () {
    $this->postJson('/api/v1/me/phone/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])->assertStatus(401);
});

it('attaches a proven phone', function () {
    $challengeId = $this->withToken($this->token)
        ->postJson('/api/v1/me/phone/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])
        ->assertOk()->json('challengeId');

    $this->withToken($this->token)
        ->postJson('/api/v1/me/phone/otp/verify', ['challengeId' => $challengeId, 'code' => $this->codes->lastCode()])
        ->assertOk()
        ->assertJson(['id' => $this->user->id, 'phone' => '+21620123456']);
});

it('refuses a phone that belongs to another account, without sending', function () {
    User::factory()->withPhone('+21620123456')->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/me/phone/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])
        ->assertStatus(409)->assertJsonPath('code', 'phone_taken');
    expect($this->codes->sent)->toBe([]);
});

it('says phone_taken if someone took the phone between send and verify', function () {
    $challengeId = $this->withToken($this->token)
        ->postJson('/api/v1/me/phone/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])->json('challengeId');
    User::factory()->withPhone('+21620123456')->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/me/phone/otp/verify', ['challengeId' => $challengeId, 'code' => $this->codes->lastCode()])
        ->assertStatus(409)->assertJsonPath('code', 'phone_taken');
});

it('does not let another account use or burn my challenge', function () {
    $challengeId = $this->withToken($this->token)
        ->postJson('/api/v1/me/phone/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])->json('challengeId');
    $code = $this->codes->lastCode();
    $this->app['auth']->forgetGuards();

    $intruder = tokenFor(User::factory()->create());
    $this->withToken($intruder)
        ->postJson('/api/v1/me/phone/otp/verify', ['challengeId' => $challengeId, 'code' => $code])
        ->assertStatus(422)->assertJsonPath('code', 'code_expired');
    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)
        ->postJson('/api/v1/me/phone/otp/verify', ['challengeId' => $challengeId, 'code' => $code])
        ->assertOk();
});

it('does not accept a sign-in challenge for attaching', function () {
    $challengeId = $this->postJson('/api/v1/auth/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])->json('challengeId');

    $this->withToken($this->token)
        ->postJson('/api/v1/me/phone/otp/verify', ['challengeId' => $challengeId, 'code' => $this->codes->lastCode()])
        ->assertStatus(422)->assertJsonPath('code', 'code_expired');
});

it('caps attach attempts per account', function () {
    config(['otp.limits.per_user_attach_per_hour' => 2]);
    foreach (['+21620000001', '+21620000002'] as $phone) {
        $this->withToken($this->token)->postJson('/api/v1/me/phone/otp/send', ['phone' => $phone, 'locale' => 'fr'])->assertOk();
    }
    $this->withToken($this->token)->postJson('/api/v1/me/phone/otp/send', ['phone' => '+21620000003', 'locale' => 'fr'])
        ->assertStatus(429);
});
