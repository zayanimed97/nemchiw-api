<?php

namespace Modules\Shared\Places;

/** A pending place was approved (status live) or rejected in the review queue. */
final class PlaceModerated
{
    public function __construct(
        public readonly string $kind,
        public readonly string $id,
        public readonly string $status,
    ) {}
}
