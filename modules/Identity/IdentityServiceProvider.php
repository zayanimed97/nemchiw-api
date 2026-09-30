<?php

namespace Modules\Identity;

use Laravel\Sanctum\Sanctum;
use Modules\Identity\Contracts\Accounts;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Services\EloquentAccounts;
use Modules\Shared\Providers\ModuleServiceProvider;

class IdentityServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Identity';
    }

    public function register(): void
    {
        parent::register();
        $this->app->singleton(Accounts::class, EloquentAccounts::class);
    }

    public function boot(): void
    {
        parent::boot();
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
