<?php

namespace Modules\Identity\Services;

use Modules\Identity\Contracts\ProfilePhotos;

/** Default when no photo module is installed. */
final class NoProfilePhotos implements ProfilePhotos
{
    public function ownerView(string $userId): ?array
    {
        return null;
    }

    public function publicView(string $userId): ?array
    {
        return null;
    }
}
