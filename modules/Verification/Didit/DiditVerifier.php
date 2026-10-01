<?php

namespace Modules\Verification\Didit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Verification\Contracts\IdentityVerifier;

/** Didit v3 sessions API. Talks only to the configured Didit host, with the key in a header. */
final class DiditVerifier implements IdentityVerifier
{
    /** @param  array{base_url: string, api_key: string, workflow_id: string}  $config */
    public function __construct(private readonly array $config) {}

    public function createSession(string $userId, string $portraitJpeg, string $language): array
    {
        $this->guardConfigured();

        $response = $this->send(fn (PendingRequest $http) => $http->post($this->config['base_url'].'/v3/session/', [
            'workflow_id' => $this->config['workflow_id'],
            'vendor_data' => $userId,
            'language' => $language,
            'portrait_image' => base64_encode($portraitJpeg),
        ]));

        $sessionId = $response->json('session_id');
        $token = $response->json('session_token');
        if (! is_string($sessionId) || $sessionId === '' || ! is_string($token) || $token === '') {
            throw self::unavailable('Didit: session response without id or token', $response->status());
        }

        return ['sessionId' => $sessionId, 'sessionToken' => $token];
    }

    public function deleteSession(string $sessionId): void
    {
        $this->guardConfigured();

        $this->send(fn (PendingRequest $http) => $http->delete(
            $this->config['base_url'].'/v3/session/'.rawurlencode($sessionId).'/delete/',
            ['deletion_instruction' => 'privacy_erasure'],
        ), notFoundIsFine: true);
    }

    /** @param  callable(PendingRequest): Response  $call */
    private function send(callable $call, bool $notFoundIsFine = false): Response
    {
        try {
            $response = $call(Http::withHeaders(['x-api-key' => $this->config['api_key']])
                ->acceptJson()->asJson()->timeout(10)->connectTimeout(3));
        } catch (ConnectionException) {
            throw self::unavailable('Didit: unreachable');
        }

        if ($response->failed() && ! ($notFoundIsFine && $response->status() === 404)) {
            throw self::unavailable('Didit: request refused', $response->status());
        }

        return $response;
    }

    private function guardConfigured(): void
    {
        if ($this->config['api_key'] === '' || $this->config['workflow_id'] === '') {
            throw self::unavailable('Didit: not configured');
        }
    }

    private static function unavailable(string $reason, ?int $status = null): ApiException
    {
        Log::warning($reason, ['status' => $status]);

        return new ApiException(ApiErrorCode::ProviderUnavailable, 'Verification is unavailable right now');
    }
}
