<?php

namespace Modules\Parties;

use Illuminate\Support\Facades\Event;
use Modules\Identity\Events\AccountDeleting;
use Modules\Parties\Console\EndIdlePartiesCommand;
use Modules\Parties\Listeners\AttachPlaceholders;
use Modules\Parties\Listeners\LeaveOnAccountDeleting;
use Modules\Shared\Events\ContactConfirmed;
use Modules\Shared\Providers\ModuleServiceProvider;

class PartiesServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Parties';
    }

    public function boot(): void
    {
        parent::boot();
        Event::listen(ContactConfirmed::class, AttachPlaceholders::class);
        Event::listen(AccountDeleting::class, LeaveOnAccountDeleting::class);

        if ($this->app->runningInConsole()) {
            $this->commands([EndIdlePartiesCommand::class]);
        }
    }
}
