<?php

namespace Modules\Verification;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Identity\Events\AccountDeleting;
use Modules\Shared\Providers\ModuleServiceProvider;
use Modules\Verification\Actions\PruneWebhookEvents;
use Modules\Verification\Contracts\IdentityVerifier;
use Modules\Verification\Didit\DiditVerifier;
use Modules\Verification\Listeners\EraseSessions;

class VerificationServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Verification';
    }

    public function register(): void
    {
        parent::register();
        $this->app->bind(IdentityVerifier::class, fn () => new DiditVerifier((array) config('verification.didit')));
    }

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('didit-webhook', fn (Request $request) => Limit::perMinute(120)->by('ip:'.$request->ip()));
        Event::listen(AccountDeleting::class, EraseSessions::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(fn () => app(PruneWebhookEvents::class)())->daily()->name('verification:prune-webhooks')->withoutOverlapping(10);
        });
    }
}
