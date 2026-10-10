<?php

namespace Tests\Support;

use Modules\Identity\Contracts\ProfilePhotos;

/** Profile photos for tests: ids in $verified have a verified photo. */
final class FakeProfilePhotos implements ProfilePhotos
{
    /** @var list<string> */
    public static array $verified = [];

    public function ownerView(string $userId): ?array
    {
        return in_array($userId, self::$verified, true) ? ['url' => "https://test/{$userId}.jpg", 'status' => 'verified'] : null;
    }

    public function publicView(string $userId): ?array
    {
        return in_array($userId, self::$verified, true) ? ['url' => "https://test/{$userId}.jpg", 'verified' => true] : null;
    }
}
