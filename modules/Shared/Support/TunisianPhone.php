<?php

namespace Modules\Shared\Support;

final class TunisianPhone
{
    /** E.164, +216 then 8 digits, first digit 2–9. Same rule as the app. */
    public const PATTERN = '/^\+216[2-9]\d{7}$/';

    /**
     * Any common way of writing a Tunisian mobile number as E.164, or null. Accepts +216,
     * 00216, 216 or no prefix, with spaces, dashes and dots.
     */
    public static function normalise(string $input): ?string
    {
        $digits = preg_replace('/[\s.\-]+/', '', $input) ?? '';

        return preg_match('/^(?:\+216|00216|216)?([2-9]\d{7})$/', $digits, $m) === 1 ? '+216'.$m[1] : null;
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }
}
