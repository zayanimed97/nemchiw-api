<?php

namespace Modules\Shared\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** The organiser changed what campers act on. `fields`: meetupAt and/or meetupUrl. */
final class TripEdited implements ShouldDispatchAfterCommit
{
    /** @param list<string> $fields */
    public function __construct(
        public readonly string $tripId,
        public readonly string $organiserId,
        public readonly array $fields,
    ) {}
}
