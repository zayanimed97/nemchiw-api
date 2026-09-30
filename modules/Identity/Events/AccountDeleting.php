<?php

namespace Modules\Identity\Events;

/** Fired inside the deletion transaction, before the user row goes. */
final class AccountDeleting
{
    public function __construct(
        public readonly string $userId,
        public readonly ?string $phone,
    ) {}
}
