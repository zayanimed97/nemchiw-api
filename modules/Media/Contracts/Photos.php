<?php

namespace Modules\Media\Contracts;

/** What other modules may do with profile photos. */
interface Photos
{
    /** The person's current photo. @return array{id: string, status: string}|null */
    public function current(string $userId): ?array;

    /** The photo as a JPEG no larger than 1024 px a side (well under Didit's 2 MB portrait limit). */
    public function portrait(string $photoId): string;

    /** Sets the verification status; a no-op when the photo no longer exists (replaced or deleted). */
    public function setStatus(string $photoId, string $status): void;
}
