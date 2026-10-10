<?php

namespace Modules\Admin;

use Modules\Admin\Console\CreateAdminCommand;
use Modules\Admin\Observers\RecordAdminAction;
use Modules\Admin\Places\EloquentPlaceDirectory;
use Modules\Admin\Places\PlaceModels;
use Modules\Shared\Places\PlaceDirectory;
use Modules\Shared\Providers\ModuleServiceProvider;

class AdminServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Admin';
    }

    public function register(): void
    {
        parent::register();
        $this->app->bind(PlaceDirectory::class, EloquentPlaceDirectory::class);
    }

    public function boot(): void
    {
        parent::boot();
        foreach (PlaceModels::ALL as $model) {
            $model::observe(RecordAdminAction::class);
        }
        $this->loadViewsFrom($this->modulePath('resources/views'), 'admin');

        if ($this->app->runningInConsole()) {
            $this->commands([CreateAdminCommand::class]);
        }
    }
}
