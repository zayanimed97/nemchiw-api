<?php

namespace Modules\Shared\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class Iso
{
    /** "2026-09-29T00:00:00.000Z": the wire format for every instant. */
    public static function format(DateTimeInterface $at): string
    {
        return CarbonImmutable::instance($at)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }
}
