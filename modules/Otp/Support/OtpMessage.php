<?php

namespace Modules\Otp\Support;

/** The SMS text. Darija follows the spelling guide in the app's CLAUDE.md. */
final class OtpMessage
{
    public static function for(string $locale, string $code): string
    {
        return match ($locale) {
            'ar' => "الكود متاعك في نمشيو: {$code}. ما تعطيه لحتّى حد.",
            'fr' => "Votre code Nemchiw : {$code}. Ne le partagez avec personne.",
            default => "Your Nemchiw code is {$code}. Don't share it with anyone.",
        };
    }
}
