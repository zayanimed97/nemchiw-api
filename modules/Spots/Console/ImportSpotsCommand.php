<?php

namespace Modules\Spots\Console;

use Illuminate\Console\Command;
use Modules\Spots\Actions\ImportSpots;

final class ImportSpotsCommand extends Command
{
    protected $signature = 'spots:import {path=modules/Spots/database/data/spots.json}';

    protected $description = 'Create or update spots from a JSON file';

    public function handle(ImportSpots $import): int
    {
        $rows = json_decode((string) file_get_contents(base_path($this->argument('path'))), true, flags: JSON_THROW_ON_ERROR);
        $counts = $import($rows);
        $this->info("created {$counts['created']}, updated {$counts['updated']}, unchanged {$counts['unchanged']}");

        return self::SUCCESS;
    }
}
