<?php

namespace Modules\Shared;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Shared\Actions\PruneExpiredCache;
use Modules\Shared\Console\BackupDatabase;
use Modules\Shared\Providers\ModuleServiceProvider;

class SharedServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Shared';
    }

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute((int) config('shared.api_per_minute'))
            ->by($request->user('sanctum')?->getAuthIdentifier() ?? $request->ip()));

        if ($this->app->runningInConsole()) {
            $this->commands([BackupDatabase::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(fn () => app(PruneExpiredCache::class)())->hourly()->name('cache:prune-expired')->withoutOverlapping(10);
        });
    }
}
