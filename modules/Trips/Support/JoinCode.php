<?php

namespace Modules\Trips\Support;

/**
 * TOTP (RFC 6238) over HMAC-SHA256, 6 digits. The organiser's phone computes the same
 * code offline from the request's secret (src/features/trips/code.ts).
 */
final class JoinCode
{
    public static function counter(int $unixSeconds): int
    {
        return intdiv($unixSeconds, (int) config('trips.code_step_seconds', 30));
    }

    /** @param string $secret raw bytes */
    public static function at(string $secret, int $counter): string
    {
        $mac = hash_hmac('sha256', pack('J', $counter), $secret, true);
        $offset = ord($mac[strlen($mac) - 1]) & 0x0F;
        $binary = ((ord($mac[$offset]) & 0x7F) << 24)
            | (ord($mac[$offset + 1]) << 16)
            | (ord($mac[$offset + 2]) << 8)
            | ord($mac[$offset + 3]);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /** @return 'ok'|'expired'|'invalid' */
    public static function check(string $secret, string $code, int $unixSeconds): string
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return 'invalid';
        }
        $now = self::counter($unixSeconds);
        $drift = (int) config('trips.code_drift_windows', 1);
        for ($c = $now - $drift; $c <= $now + $drift; $c++) {
            if (hash_equals(self::at($secret, $c), $code)) {
                return 'ok';
            }
        }
        $back = (int) config('trips.code_expired_windows', 20);
        for ($c = $now - $back; $c < $now - $drift; $c++) {
            if (hash_equals(self::at($secret, $c), $code)) {
                return 'expired';
            }
        }

        return 'invalid';
    }
}
