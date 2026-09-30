<?php

namespace Modules\Spots;

use Modules\Shared\Providers\ModuleServiceProvider;
use Modules\Spots\Console\ImportSpotsCommand;

class SpotsServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Spots';
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([ImportSpotsCommand::class]);
        }
    }
}
