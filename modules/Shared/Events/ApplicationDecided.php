<?php

namespace Modules\Shared\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** The admin decided an organiser application. `outcome`: approved, rejected, revoked. */
final class ApplicationDecided implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly string $userId, public readonly string $outcome) {}
}
