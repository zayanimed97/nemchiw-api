<?php

namespace Modules\Otp\Support;

final class OtpCode
{
    public static function generate(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    /** Keyed with APP_KEY: a leaked table alone cannot be brute-forced offline. */
    public static function hash(string $challengeId, string $code): string
    {
        return hash_hmac('sha256', $challengeId.'|'.$code, (string) config('app.key'));
    }
}
