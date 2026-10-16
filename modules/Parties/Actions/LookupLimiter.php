<?php

namespace Modules\Parties\Actions;

use Illuminate\Support\Facades\RateLimiter;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

/** Every "does this contact have an account?" question counts: lookups and invites by contact. */
final class LookupLimiter
{
    public function hit(string $userId): void
    {
        $limits = [
            ["parties-lookup:{$userId}", (int) config('parties.lookups_per_hour'), 3600],
            ["parties-lookup-day:{$userId}", (int) config('parties.lookups_per_day'), 86400],
        ];
        // hit() increments atomically and returns the new count, so concurrent requests cannot slip past.
        $over = null;
        foreach ($limits as [$key, $max, $seconds]) {
            if (RateLimiter::hit($key, $seconds) > $max) {
                $over ??= $key;
            }
        }
        if ($over !== null) {
            throw new ApiException(ApiErrorCode::LookupLimited, 'Too many searches. Try again later', RateLimiter::availableIn($over));
        }
    }
}
