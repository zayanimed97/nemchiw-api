<?php

use Modules\Identity\Models\User;
use Modules\Otp\Actions\PruneChallenges;
use Modules\Otp\Contracts\SmsSender;
use Modules\Otp\Models\OtpChallenge;
use Modules\Otp\Testing\FakeSmsSender;

beforeEach(fn () => $this->app->instance(SmsSender::class, new FakeSmsSender));

it('forgets challenges when the account is deleted', function () {
    $user = User::factory()->withPhone('+21620123456')->create();
    $token = tokenFor($user);
    $this->postJson('/api/v1/auth/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])->assertOk();
    $this->withToken($token)->postJson('/api/v1/me/phone/otp/send', ['phone' => '+21655000111', 'locale' => 'fr'])->assertOk();

    $this->withToken($token)->deleteJson('/api/v1/me')->assertNoContent();

    expect(OtpChallenge::count())->toBe(0);
});

it('prunes challenges older than a day', function () {
    $this->postJson('/api/v1/auth/otp/send', ['phone' => '+21620000001', 'locale' => 'fr']);
    $this->travel(25)->hours();
    $this->postJson('/api/v1/auth/otp/send', ['phone' => '+21620000002', 'locale' => 'fr']);

    app(PruneChallenges::class)();

    expect(OtpChallenge::pluck('phone')->all())->toBe(['+21620000002']);
});
