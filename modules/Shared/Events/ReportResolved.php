<?php

namespace Modules\Shared\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** The admin resolved a report on a request; the reporter hears the outcome. */
final class ReportResolved implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $tripId,
        public readonly string $reporterId,
        public readonly string $outcome,
    ) {}
}
