<?php

namespace Modules\Trips\Support;

final class RequestStatus
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const JOINED = 'joined';

    public const DECLINED = 'declined';

    public const WITHDRAWN = 'withdrawn';

    public const CLOSED = 'closed';

    public const CANCELLED = 'cancelled';

    public const LAPSED = 'lapsed';

    public const OPEN = [self::PENDING, self::ACCEPTED, self::JOINED];

    /** After these the same person may ask again. */
    public const MAY_REQUEST_AGAIN = [self::DECLINED, self::WITHDRAWN, self::LAPSED];
}
