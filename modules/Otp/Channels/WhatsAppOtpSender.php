<?php

namespace Modules\Otp\Channels;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Otp\Contracts\OtpSender;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use RuntimeException;

/**
 * Sends the code as Meta's WhatsApp authentication template (fixed body, copy-code
 * button) through the Cloud API. Only ever talks to graph.facebook.com.
 */
final class WhatsAppOtpSender implements OtpSender
{
    /** @param  array{token: string, phone_number_id: string, template: string, graph_version: string, languages: array<string, string>}  $config */
    public function __construct(private readonly array $config)
    {
        foreach (['token', 'phone_number_id', 'template', 'graph_version'] as $key) {
            if (($config[$key] ?? '') === '') {
                throw new RuntimeException("WhatsApp OTP channel needs otp.whatsapp.{$key}");
            }
        }
    }

    public function send(string $phone, string $code, string $locale): void
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            rawurlencode($this->config['graph_version']),
            rawurlencode($this->config['phone_number_id']),
        );

        try {
            $response = Http::withToken($this->config['token'])
                ->acceptJson()
                ->timeout(5)
                ->connectTimeout(3)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($phone, '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => $this->config['template'],
                        'language' => ['code' => $this->config['languages'][$locale] ?? $locale],
                        'components' => [
                            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                            // The copy-code button carries the code as its single parameter.
                            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            Log::warning('WhatsApp OTP: Meta unreachable');

            throw new ApiException(ApiErrorCode::ProviderUnavailable, 'Could not send the code');
        }

        if ($response->failed()) {
            // Meta's error code and title only: the body never contains the code, but keep logs lean.
            Log::warning('WhatsApp OTP: send refused', [
                'status' => $response->status(),
                'meta_code' => $response->json('error.code'),
                'meta_message' => $response->json('error.message'),
            ]);

            throw new ApiException(ApiErrorCode::ProviderUnavailable, 'Could not send the code');
        }
    }
}
