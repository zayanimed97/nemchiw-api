<?php

namespace Modules\Shared;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user('sanctum')?->getAuthIdentifier() ?? $request->ip()));
    }
}
