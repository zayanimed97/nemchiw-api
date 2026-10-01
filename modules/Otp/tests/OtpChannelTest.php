<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Otp\Channels\LogOtpSender;
use Modules\Otp\Contracts\OtpSender;
use Modules\Otp\Models\OtpChallenge;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

function useWhatsApp(): void
{
    config([
        'otp.channel' => 'whatsapp',
        'otp.whatsapp' => [
            'token' => 'test-token',
            'phone_number_id' => '1234567890',
            'template' => 'nemchiw_code',
            'graph_version' => 'v23.0',
            'languages' => ['ar' => 'ar', 'fr' => 'fr', 'en' => 'en'],
        ],
    ]);
    app()->forgetInstance(OtpSender::class);
}

it('uses the log channel outside production', function () {
    expect(app(OtpSender::class))->toBeInstanceOf(LogOtpSender::class);
});

it('fails the request, not silently afterwards, when production has no real channel', function () {
    $this->app['env'] = 'production';

    $this->postJson('/api/v1/auth/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])
        ->assertStatus(500)->assertJsonPath('code', 'server');
    expect(OtpChallenge::count())->toBe(0);
});

it('sends the authentication template over WhatsApp', function () {
    useWhatsApp();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]])]);

    $this->postJson('/api/v1/auth/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])->assertOk();

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $code = $body['template']['components'][0]['parameters'][0]['text'] ?? '';

        return $request->url() === 'https://graph.facebook.com/v23.0/1234567890/messages'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $body['messaging_product'] === 'whatsapp'
            && $body['to'] === '21620123456'
            && $body['type'] === 'template'
            && $body['template']['name'] === 'nemchiw_code'
            && $body['template']['language']['code'] === 'fr'
            && preg_match('/^\d{6}$/', $code) === 1
            && $body['template']['components'][1] == [
                'type' => 'button', 'sub_type' => 'url', 'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $code]],
            ];
    });
});

it('maps the app language to the template language', function () {
    useWhatsApp();
    config(['otp.whatsapp.languages.en' => 'en_US']);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]])]);

    app(OtpSender::class)->send('+21620123456', '123456', 'en');

    Http::assertSent(fn (Request $request) => $request->data()['template']['language']['code'] === 'en_US');
});

it('answers provider_unavailable when WhatsApp refuses, and charges nothing', function () {
    useWhatsApp();
    config(['otp.limits.per_phone_per_hour' => 1]);
    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['error' => ['code' => 132001, 'message' => 'Template does not exist']], 404)
        ->push(['messages' => [['id' => 'wamid.X']]])]);

    $this->postJson('/api/v1/auth/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])
        ->assertStatus(503)->assertJsonPath('code', 'provider_unavailable');
    expect(OtpChallenge::count())->toBe(0);

    $this->postJson('/api/v1/auth/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])->assertOk();
});

it('answers provider_unavailable when WhatsApp cannot be reached', function () {
    useWhatsApp();
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $this->postJson('/api/v1/auth/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])
        ->assertStatus(503)->assertJsonPath('code', 'provider_unavailable');
});

it('refuses to start without WhatsApp credentials', function () {
    useWhatsApp();
    config(['otp.whatsapp.token' => '']);

    expect(fn () => app(OtpSender::class))->toThrow(RuntimeException::class);
});

it('charges the hourly budget before sending, and refunds it when the send fails', function () {
    $seen = null;
    app()->instance(OtpSender::class, new class($seen) implements OtpSender
    {
        public function __construct(public ?int &$seen) {}

        public function send(string $phone, string $code, string $locale): void
        {
            $this->seen = RateLimiter::attempts('otp:global');
            throw new ApiException(ApiErrorCode::ProviderUnavailable, 'down');
        }
    });

    $this->postJson('/api/v1/auth/otp/send', ['phone' => '+21620123456', 'locale' => 'fr'])->assertStatus(503);

    expect($seen)->toBe(1);
    expect(RateLimiter::attempts('otp:global'))->toBe(0);
    expect(RateLimiter::attempts('otp:phone:+21620123456'))->toBe(0);
});
