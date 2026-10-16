<?php

namespace Modules\Shared\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** A phone or email became verifiable for this account. `contactHash` is ContactHash::of the normalised contact. */
final class ContactConfirmed implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly string $userId, public readonly string $contactHash) {}
}
