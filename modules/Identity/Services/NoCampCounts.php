<?php

namespace Modules\Identity\Services;

use Modules\Identity\Contracts\CampCounts;

/** Default when no trips module is installed. */
final class NoCampCounts implements CampCounts
{
    public function for(string $userId): int
    {
        return 0;
    }
}
