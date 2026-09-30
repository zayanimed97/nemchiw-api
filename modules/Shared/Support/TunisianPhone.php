<?php

namespace Modules\Shared\Support;

final class TunisianPhone
{
    /** E.164, +216 then 8 digits, first digit 2–9. Same rule as the app. */
    public const PATTERN = '/^\+216[2-9]\d{7}$/';

    public static function isValid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }
}
