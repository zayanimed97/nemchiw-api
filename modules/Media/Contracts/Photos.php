<?php

namespace Modules\Media\Contracts;

/** What other modules may do with profile photos. */
interface Photos
{
    /** The person's current photo. @return array{id: string, status: string}|null */
    public function current(string $userId): ?array;

    /** The stored JPEG bytes of a photo. */
    public function jpeg(string $photoId): string;

    /** Sets the verification status; a no-op when the photo no longer exists (replaced or deleted). */
    public function setStatus(string $photoId, string $status): void;
}
