<?php

namespace Modules\Trips;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Identity\Contracts\CampCounts;
use Modules\Shared\Places\PlaceModerated;
use Modules\Shared\Providers\ModuleServiceProvider;
use Modules\Trips\Actions\Tick;
use Modules\Trips\Listeners\SettleDraftTrips;
use Modules\Trips\Services\TripCampCounts;

class TripsServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Trips';
    }

    public function register(): void
    {
        parent::register();
        $this->app->singleton(CampCounts::class, TripCampCounts::class);
    }

    public function boot(): void
    {
        parent::boot();
        RateLimiter::for('place-submissions', fn (Request $request) => Limit::perDay(10)->by('user:'.$request->user()?->getAuthIdentifier()));
        Event::listen(PlaceModerated::class, SettleDraftTrips::class);
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(fn () => app(Tick::class)())->hourly()->name('trips:tick')->withoutOverlapping(10);
        });
    }
}
