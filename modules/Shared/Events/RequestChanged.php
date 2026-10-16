<?php

namespace Modules\Shared\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A trip request moved. `change`: asked, accepted, declined, withdrawn, cancelled, closed,
 * scanned, lapsed, trip_cancelled, and for parties member_left, member_removed, leader_changed (none of
 * these ends the request; `note` is the person's id). `by`: camper, organiser, admin, system. Dispatched after
 * the transaction commits, so a rolled-back change tells nobody.
 */
final class RequestChanged implements ShouldDispatchAfterCommit
{
    /** Changes after which the request is over (and its conversation read-only). */
    public const ENDINGS = ['declined', 'withdrawn', 'cancelled', 'closed', 'lapsed', 'trip_cancelled'];

    public function __construct(
        public readonly string $requestId,
        public readonly string $tripId,
        public readonly string $leaderId,
        public readonly string $organiserId,
        public readonly string $change,
        public readonly string $by,
        public readonly ?string $note = null,
    ) {}
}
