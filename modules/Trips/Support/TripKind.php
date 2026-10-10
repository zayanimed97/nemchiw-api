<?php

namespace Modules\Trips\Support;

final class TripKind
{
    public const CAMPING = 'camping';

    public const HIKE_PRO = 'hike_pro';

    public const HIKE_COMMUNITY = 'hike_community';

    public const ALL = [self::CAMPING, self::HIKE_PRO, self::HIKE_COMMUNITY];

    /** Kinds only a certified organiser may publish (paid, with the monthly fee in 3.5). */
    public const CERTIFIED = [self::CAMPING, self::HIKE_PRO];
}
