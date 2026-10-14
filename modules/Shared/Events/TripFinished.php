<?php

namespace Modules\Shared\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** The hourly tick marked the trip finished (its end has passed). */
final class TripFinished implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly string $tripId) {}
}
