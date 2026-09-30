<?php

namespace Modules\Shared\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Base for every module. Merges modules/<X>/config.php under the key snake(<X>),
 * loads its migrations, and mounts routes/api.php under /api/v1 in the `api` group.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** Folder under modules/, e.g. "Spots". */
    abstract protected function module(): string;

    protected function modulePath(string $path = ''): string
    {
        return base_path('modules/'.$this->module().($path === '' ? '' : '/'.$path));
    }

    public function register(): void
    {
        $config = $this->modulePath('config.php');
        if (is_file($config)) {
            $this->mergeConfigFrom($config, Str::snake($this->module()));
        }
    }

    public function boot(): void
    {
        $migrations = $this->modulePath('database/migrations');
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        $routes = $this->modulePath('routes/api.php');
        if (is_file($routes) && ! $this->app->routesAreCached()) {
            Route::prefix('api/v1')->middleware('api')->group($routes);
        }
    }
}
