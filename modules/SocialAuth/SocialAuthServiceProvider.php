<?php

namespace Modules\SocialAuth;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Identity\Contracts\LinkedProviders;
use Modules\Identity\Events\AccountDeleting;
use Modules\Shared\Providers\ModuleServiceProvider;
use Modules\SocialAuth\Listeners\ForgetIdentities;
use Modules\SocialAuth\Services\EloquentLinkedProviders;

class SocialAuthServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'SocialAuth';
    }

    public function register(): void
    {
        parent::register();
        $this->app->singleton(LinkedProviders::class, EloquentLinkedProviders::class);
    }

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('social', fn (Request $request) => Limit::perMinutes(10, (int) config('social_auth.per_ip_per_10_min'))
            ->by('ip:'.$request->ip()));

        Event::listen(AccountDeleting::class, ForgetIdentities::class);
    }
}
