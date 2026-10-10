<?php

namespace Modules\Admin;

use Illuminate\Support\Facades\Route;
use Modules\Admin\Console\CreateAdminCommand;
use Modules\Admin\Http\OrganiserDocumentController;
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
        // Organiser documents never sit on a public disk: an admin streams them here, each view logged.
        Route::middleware(['web', 'auth:admin'])
            ->get('admin/documents/{document}', OrganiserDocumentController::class)
            ->where('document', '[0-9A-Za-z]{26}')
            ->name('admin.organiser-document');
        $this->loadViewsFrom($this->modulePath('resources/views'), 'admin');

        if ($this->app->runningInConsole()) {
            $this->commands([CreateAdminCommand::class]);
        }
    }
}
