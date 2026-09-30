<?php

use Modules\Otp\Contracts\SmsSender;
use Modules\Otp\Models\OtpChallenge;
use Modules\Otp\Sms\LogSmsSender;

it('uses the log driver outside production', function () {
    expect(app(SmsSender::class))->toBeInstanceOf(LogSmsSender::class);
});

it('refuses to log codes in production', function () {
    $this->app['env'] = 'production';
    expect(fn () => (new LogSmsSender)->send('+21620123456', 'code 123456'))->toThrow(RuntimeException::class);
});

it('fails the request, not silently afterwards, when production has no real SMS driver', function () {
    $this->app['env'] = 'production';

    $this->postJson('/api/v1/auth/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])
        ->assertStatus(500)->assertJsonPath('code', 'server');
    expect(OtpChallenge::count())->toBe(0);
});
