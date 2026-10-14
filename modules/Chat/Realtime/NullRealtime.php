<?php

namespace Modules\Chat\Realtime;

use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

/** Realtime switched off (no keys): nothing is published and the app falls back to polling. */
final class NullRealtime implements Realtime
{
    public function publish(string $channel, string $event, array $payload): void {}

    public function authorize(string $channel, string $socketId): array
    {
        throw new ApiException(ApiErrorCode::ProviderUnavailable, 'Realtime is not configured');
    }
}
