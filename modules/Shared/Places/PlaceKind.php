<?php

namespace Modules\Shared\Places;

/** The three kinds of place on the map. Photos and trips (3.2) refer to a place as (kind, id). */
final class PlaceKind
{
    public const SPOT = 'spot';

    public const TRAIL = 'trail';

    public const CENTRE = 'centre';

    public const ALL = [self::SPOT, self::TRAIL, self::CENTRE];
}
