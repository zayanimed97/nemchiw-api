<?php

namespace Modules\Identity;

use Laravel\Sanctum\Sanctum;
use Modules\Identity\Contracts\Accounts;
use Modules\Identity\Contracts\CampCounts;
use Modules\Identity\Contracts\LinkedProviders;
use Modules\Identity\Contracts\People;
use Modules\Identity\Contracts\ProfilePhotos;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Services\EloquentAccounts;
use Modules\Identity\Services\EloquentPeople;
use Modules\Identity\Services\NoCampCounts;
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
        // Not a singleton: it reads ProfilePhotos, which tests swap.
        $this->app->bind(People::class, EloquentPeople::class);
        $this->app->singletonIf(CampCounts::class, NoCampCounts::class);
    }

    public function boot(): void
    {
        parent::boot();
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
