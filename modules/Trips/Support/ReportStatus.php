<?php

namespace Modules\Trips\Support;

final class ReportStatus
{
    public const OPEN = 'open';

    public const CLOSED_NO_PENALTY = 'closed_no_penalty';

    public const CLOSED_ORGANISER = 'closed_organiser';

    public const DISMISSED = 'dismissed';

    public const OUTCOMES = [self::CLOSED_NO_PENALTY, self::CLOSED_ORGANISER, self::DISMISSED];
}
