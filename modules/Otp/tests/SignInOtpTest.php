<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Identity\Models\User;
use Modules\Otp\Contracts\SmsSender;
use Modules\Otp\Testing\FakeSmsSender;

beforeEach(function () {
    $this->sms = new FakeSmsSender;
    $this->app->instance(SmsSender::class, $this->sms);
});

function sendCode(string $phone = '+21620123456', string $locale = 'fr', array $headers = [])
{
    return test()->postJson('/api/v1/auth/otp/send', ['phone' => $phone, 'locale' => $locale], $headers);
}

it('sends a code and returns the challenge', function () {
    $response = sendCode()->assertOk()->assertJson(['resendAfter' => 60, 'expiresIn' => 300]);

    expect($response->json('challengeId'))->toHaveLength(26);
    expect($this->sms->sent)->toHaveCount(1);
    expect($this->sms->sent[0]['phone'])->toBe('+21620123456');
    $code = $this->sms->lastCode();
    expect($code)->toMatch('/^\d{6}$/');
    expect(DB::table('otp_challenges')->value('code_hash'))->not->toContain($code);
});

it('writes the SMS in the requested language', function () {
    sendCode(locale: 'ar')->assertOk();
    expect($this->sms->sent[0]['message'])->toContain('نمشيو');
});

it('rejects anything but a Tunisian mobile number', function (string $phone) {
    sendCode($phone)->assertStatus(422)->assertJsonPath('code', 'validation');
    expect($this->sms->sent)->toBe([]);
})->with(['+216 20 123 456', '20123456', '+21610123456', '+33612345678', '']);

it('answers the same way for known and unknown phones', function () {
    User::factory()->withPhone('+21620123456')->create();
    $known = sendCode('+21620123456')->json();
    $unknown = sendCode('+21620999999')->json();

    unset($known['challengeId'], $unknown['challengeId']);
    expect($known)->toBe($unknown);
});

it('signs in with the right code, once', function () {
    $challengeId = sendCode()->json('challengeId');
    $code = $this->sms->lastCode();

    $this->postJson('/api/v1/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $code])
        ->assertOk()
        ->assertJson(['isNew' => true, 'profile' => ['phone' => '+21620123456']])
        ->assertJsonStructure(['token']);

    $this->postJson('/api/v1/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $code])
        ->assertStatus(422)->assertJsonPath('code', 'code_expired');
});

it('returns isNew false for an existing phone', function () {
    $user = User::factory()->withPhone('+21620123456')->create();
    $challengeId = sendCode()->json('challengeId');

    $this->postJson('/api/v1/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $this->sms->lastCode()])
        ->assertOk()->assertJson(['isNew' => false, 'profile' => ['id' => $user->id]]);
});

it('says invalid_code for a wrong code and locks after 5 attempts', function () {
    $challengeId = sendCode()->json('challengeId');
    $code = $this->sms->lastCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $wrong])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_code');
    }
    $this->postJson('/api/v1/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $code])
        ->assertStatus(429)->assertJsonPath('code', 'rate_limited');
});

it('expires codes after 5 minutes', function () {
    $challengeId = sendCode()->json('challengeId');
    $this->travel(301)->seconds();

    $this->postJson('/api/v1/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $this->sms->lastCode()])
        ->assertStatus(422)->assertJsonPath('code', 'code_expired');
});

it('makes you wait 60 s between codes', function () {
    sendCode()->assertOk();

    sendCode()->assertStatus(429)->assertJsonPath('code', 'rate_limited');
    expect(sendCode()->json('retryAfter'))->toBeLessThanOrEqual(60)->toBeGreaterThan(0);

    $this->travel(61)->seconds();
    sendCode()->assertOk();
});

it('keeps the code someone is typing alive when another is requested', function () {
    $first = sendCode()->json('challengeId');
    $firstCode = $this->sms->lastCode();
    $this->travel(61)->seconds();
    sendCode()->assertOk();

    $this->postJson('/api/v1/auth/otp/verify', ['challengeId' => $first, 'code' => $firstCode])->assertOk();
});

it('keeps at most 3 live codes per phone', function () {
    $first = sendCode()->json('challengeId');
    $firstCode = $this->sms->lastCode();
    foreach (range(1, 3) as $_) {
        $this->travel(61)->seconds();
        sendCode()->assertOk();
    }

    $this->postJson('/api/v1/auth/otp/verify', ['challengeId' => $first, 'code' => $firstCode])
        ->assertStatus(422)->assertJsonPath('code', 'code_expired');
});

it('warns once when half the hourly SMS budget is spent', function () {
    config(['otp.limits.global_per_hour' => 4]);
    Log::spy();

    foreach (['+21620000001', '+21620000002', '+21620000003'] as $phone) {
        sendCode($phone)->assertOk();
    }

    Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message) => str_contains($message, 'SMS budget'));
});

it('caps codes per phone per hour', function () {
    config(['otp.limits.per_phone_per_hour' => 2]);
    sendCode()->assertOk();
    $this->travel(61)->seconds();
    sendCode()->assertOk();
    $this->travel(61)->seconds();
    sendCode()->assertStatus(429);
    expect($this->sms->sent)->toHaveCount(2);
});

it('caps codes for the whole service per hour', function () {
    config(['otp.limits.global_per_hour' => 2]);
    sendCode('+21620000001')->assertOk();
    sendCode('+21620000002')->assertOk();
    sendCode('+21620000003')->assertStatus(429);
});

it('caps sends per IP, and a forged X-Forwarded-For does not reset it', function () {
    config(['otp.limits.per_ip_per_hour' => 2]);
    sendCode('+21620000001')->assertOk();
    sendCode('+21620000002')->assertOk();
    sendCode('+21620000003', headers: ['X-Forwarded-For' => '203.0.113.9'])->assertStatus(429);
});

it('rejects malformed verify input', function (array $body) {
    $this->postJson('/api/v1/auth/otp/verify', $body)->assertStatus(422)->assertJsonPath('code', 'validation');
})->with([
    [['challengeId' => 'nope', 'code' => '123456']],
    [['challengeId' => '01J8Z3Q4X5Y6Z7A8B9C0D1E2F3', 'code' => '12345']],
    [['challengeId' => '01J8Z3Q4X5Y6Z7A8B9C0D1E2F3', 'code' => 'abcdef']],
]);

it('locks a phone for a day after 10 wrong codes across challenges', function () {
    foreach (range(1, 2) as $round) {
        $challengeId = sendCode()->json('challengeId');
        $wrong = $this->sms->lastCode() === '000000' ? '111111' : '000000';
        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $wrong])->assertStatus(422);
        }
        $this->travel(61)->seconds();
    }

    sendCode()->assertStatus(429)->assertJsonPath('code', 'rate_limited');
    expect(sendCode()->json('retryAfter'))->toBeGreaterThan(80_000);

    $this->travel(24)->hours();
    sendCode()->assertOk();
});

it('refuses even the right code while the phone is locked', function () {
    config(['otp.limits.failures_per_phone_per_day' => 2]);
    $challengeId = sendCode()->json('challengeId');
    $code = $this->sms->lastCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 2) as $_) {
        $this->postJson('/api/v1/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $wrong])->assertStatus(422);
    }
    $this->postJson('/api/v1/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $code])
        ->assertStatus(429)->assertJsonPath('code', 'rate_limited');
});
