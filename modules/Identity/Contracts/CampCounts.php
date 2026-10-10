<?php

namespace Modules\Identity\Contracts;

/** Trips a person joined (code scanned) that have finished. Implemented by the module that owns trips. */
interface CampCounts
{
    public function for(string $userId): int;
}
