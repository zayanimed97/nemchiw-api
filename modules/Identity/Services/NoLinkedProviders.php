<?php

namespace Modules\Identity\Services;

use Modules\Identity\Contracts\LinkedProviders;

/** Default when no social sign-in module is installed. */
final class NoLinkedProviders implements LinkedProviders
{
    public function for(string $userId): array
    {
        return [];
    }
}
