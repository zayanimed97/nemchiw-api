<?php

namespace Modules\Organisers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Organisers\Actions\PurgeDecidedDocuments;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Organisers\Services\EloquentOrganiserRecords;
use Modules\Shared\Providers\ModuleServiceProvider;

class OrganisersServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Organisers';
    }

    public function register(): void
    {
        parent::register();
        $this->app->singleton(OrganiserRecords::class, EloquentOrganiserRecords::class);
    }

    public function boot(): void
    {
        parent::boot();
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(fn () => app(PurgeDecidedDocuments::class)())->hourly()->name('organisers:purge-documents')->withoutOverlapping(10);
        });
    }
}
