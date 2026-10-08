<?php

namespace Modules\Shared\Places;

/** Only live places reach the app. Pending ones wait for review (fed by trips, slice 3.2). */
final class PlaceStatus
{
    public const LIVE = 'live';

    public const PENDING = 'pending';

    public const REJECTED = 'rejected';

    public const HIDDEN = 'hidden';

    public const ALL = [self::LIVE, self::PENDING, self::REJECTED, self::HIDDEN];
}
