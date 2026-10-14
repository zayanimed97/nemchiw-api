<?php

namespace Modules\Shared\Places;

/**
 * A pending place was approved (status live) or rejected in the review queue.
 * `createdBy` and `name` let the person who added it hear about it.
 */
final class PlaceModerated
{
    /** @param array<string, string> $name localized */
    public function __construct(
        public readonly string $kind,
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $createdBy = null,
        public readonly array $name = [],
    ) {}
}
