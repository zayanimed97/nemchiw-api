<?php

namespace Modules\Shared\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** Once per trip, about a day before it starts. */
final class TripReminderDue implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly string $tripId) {}
}
