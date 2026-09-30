<?php

namespace Modules\Identity\Profile;

/** Same lists and limits as src/api/types.ts and src/api/profile.ts. */
final class ProfileOptions
{
    public const SKILLS = [
        'fireStarting', 'campfireCooking', 'tentSetup', 'firstAid', 'navigation', 'survival', 'swimming',
        'offroadDriving', 'fishing', 'knots', 'stargazing', 'campfireMusic', 'photography', 'hiking',
    ];

    public const LEVELS = ['beginner', 'intermediate', 'expert'];

    public const GENDERS = ['male', 'female'];

    public const NAME_MAX = 40;

    public const BIO_MAX = 160;

    public const CITY_MAX = 60;

    public const SEATS_MAX = 8;
}
