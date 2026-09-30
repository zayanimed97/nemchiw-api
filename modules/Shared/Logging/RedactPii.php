<?php

namespace Modules\Shared\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/** Keeps phone numbers, OTP codes, tokens and secrets out of log files. */
final class RedactPii implements ProcessorInterface
{
    private const SECRET_KEYS = [
        'code', 'otp', 'token', 'password', 'authorization', 'api_key', 'apikey',
        'secret', 'phone', 'session_token', 'sessiontoken', 'emergency_contact',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(message: self::scrub($record->message), context: self::scrubArray($record->context));
    }

    public static function scrub(string $text): string
    {
        $text = (string) preg_replace('/\+?216\s?[2-9]\d{7}/', '+216******', $text);
        $text = (string) preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $text);

        return (string) preg_replace('/nemchiw_[A-Za-z0-9]+/', '[token]', $text);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true)) {
                $data[$key] = '[redacted]';
            } elseif (is_string($value)) {
                $data[$key] = self::scrub($value);
            } elseif (is_array($value)) {
                $data[$key] = self::scrubArray($value);
            }
        }

        return $data;
    }
}
