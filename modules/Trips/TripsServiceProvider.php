<?php

namespace Modules\Trips;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Shared\Providers\ModuleServiceProvider;

class TripsServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Trips';
    }

    public function boot(): void
    {
        parent::boot();
        RateLimiter::for('place-submissions', fn (Request $request) => Limit::perDay(10)->by('user:'.$request->user()?->getAuthIdentifier()));
    }
}
