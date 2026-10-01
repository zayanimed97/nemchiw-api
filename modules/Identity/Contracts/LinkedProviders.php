<?php

namespace Modules\Identity\Contracts;

/** Which sign-in providers an account is linked to. Implemented by the module that owns them. */
interface LinkedProviders
{
    /** @return list<'google'|'apple'|'facebook'> */
    public function for(string $userId): array;
}
