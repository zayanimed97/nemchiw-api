<?php

namespace Modules\Identity\Contracts;

/** A person's profile photo as the profile shows it. Implemented by the module that stores photos. */
interface ProfilePhotos
{
    /** @return array{url: string, status: 'unverified'|'pending'|'verified'|'rejected'}|null */
    public function ownerView(string $userId): ?array;

    /** Only a verified photo is ever shown to other people. @return array{url: string, verified: true}|null */
    public function publicView(string $userId): ?array;
}
