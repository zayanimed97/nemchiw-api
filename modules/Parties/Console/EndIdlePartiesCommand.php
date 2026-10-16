<?php

namespace Modules\Parties\Console;

use Illuminate\Console\Command;
use Modules\Parties\Actions\EndIdleParties;

final class EndIdlePartiesCommand extends Command
{
    protected $signature = 'parties:end-idle';

    protected $description = 'End forming parties nobody touched for 30 days';

    public function handle(EndIdleParties $end): int
    {
        $this->info('Ended '.$end().' idle parties.');

        return self::SUCCESS;
    }
}
