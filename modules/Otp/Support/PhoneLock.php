<?php

namespace Modules\Otp\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Wrong codes counted per phone across all its challenges. Past the daily cap
 * the phone can neither get nor use a code for 24 h: per-challenge attempt
 * limits alone would let an attacker keep guessing with fresh challenges.
 */
final class PhoneLock
{
    private const DECAY = 86_400;

    public static function recordFailure(string $phone): void
    {
        RateLimiter::hit(self::key($phone), self::DECAY);
    }

    /** Seconds until the phone unlocks, or null when it is not locked. */
    public static function lockedFor(string $phone): ?int
    {
        $key = self::key($phone);

        return RateLimiter::tooManyAttempts($key, (int) config('otp.limits.failures_per_phone_per_day'))
            ? max(1, RateLimiter::availableIn($key))
            : null;
    }

    private static function key(string $phone): string
    {
        return "otp:fail:{$phone}";
    }
}
