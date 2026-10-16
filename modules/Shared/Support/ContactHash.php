<?php

namespace Modules\Shared\Support;

use RuntimeException;

/** A phone or email as the lookup and placeholders see it: normalised, hashed, masked. */
final class ContactHash
{
    /** A Tunisian phone as E.164, else a lower-case email, else null. */
    public static function normalise(string $input): ?string
    {
        $phone = TunisianPhone::normalise($input);
        if ($phone !== null) {
            return $phone;
        }

        $email = mb_strtolower(trim($input));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /** Hex HMAC-SHA256 of an already normalised contact, keyed by `parties.contact_key`. */
    public static function of(string $normalised): string
    {
        $key = config('parties.contact_key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('parties.contact_key is not set (env PARTIES_CONTACT_KEY).');
        }

        return hash_hmac('sha256', $normalised, $key);
    }

    /** `+216 98 ··· 432` or `a···@gmail.com`. */
    public static function mask(string $normalised): string
    {
        if (str_starts_with($normalised, '+216') && strlen($normalised) === 12) {
            return '+216 '.substr($normalised, 4, 2).' ··· '.substr($normalised, -3);
        }

        $at = strrpos($normalised, '@');
        if ($at === false) {
            return mb_substr($normalised, 0, 1).'···';
        }

        return mb_substr($normalised, 0, 1).'···'.substr($normalised, $at);
    }
}
