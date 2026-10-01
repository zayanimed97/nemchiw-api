<?php

namespace Modules\Verification\Didit;

/**
 * Didit signs each webhook with HMAC-SHA256 (hex) and a timestamp. `X-Signature` covers
 * the raw body; `X-Signature-V2` covers a canonical JSON form that survives re-encoding.
 * Either one valid is enough; both are compared in constant time.
 */
final class WebhookSignature
{
    public function __construct(
        private readonly string $secret,
        private readonly int $toleranceSeconds,
    ) {}

    public function verify(string $rawBody, ?string $timestamp, ?string $signature, ?string $signatureV2): bool
    {
        if ($this->secret === '' || $timestamp === null || ! ctype_digit($timestamp)
            || abs(now()->getTimestamp() - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        if ($signature !== null && hash_equals(hash_hmac('sha256', $rawBody, $this->secret), $signature)) {
            return true;
        }

        $payload = json_decode($rawBody, true);
        if ($signatureV2 === null || ! is_array($payload)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', self::canonical($payload), $this->secret), $signatureV2);
    }

    /** Keys sorted at every level, no spaces, unicode and slashes as-is, 100.0 written as 100. */
    public static function canonical(mixed $value): string
    {
        return (string) json_encode(self::normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return (int) $value;
        }
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::normalize(...), $value);
    }
}
