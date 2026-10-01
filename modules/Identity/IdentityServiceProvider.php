<?php

namespace Modules\Identity;

use Laravel\Sanctum\Sanctum;
use Modules\Identity\Contracts\Accounts;
use Modules\Identity\Contracts\LinkedProviders;
use Modules\Identity\Contracts\ProfilePhotos;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Services\EloquentAccounts;
use Modules\Identity\Services\NoLinkedProviders;
use Modules\Identity\Services\NoProfilePhotos;
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
        // Extension point: SocialAuth replaces this when it is installed.
        $this->app->singletonIf(LinkedProviders::class, NoLinkedProviders::class);
        $this->app->singletonIf(ProfilePhotos::class, NoProfilePhotos::class);
    }

    public function boot(): void
    {
        parent::boot();
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
