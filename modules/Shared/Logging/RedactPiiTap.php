<?php

namespace Modules\Shared\Logging;

use Illuminate\Log\Logger;

final class RedactPiiTap
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(new RedactPii);
    }
}
