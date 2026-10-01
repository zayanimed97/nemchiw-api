<?php

namespace Modules\Media;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Identity\Contracts\ProfilePhotos;
use Modules\Identity\Events\AccountDeleting;
use Modules\Media\Contracts\Photos;
use Modules\Media\Listeners\ForgetPhoto;
use Modules\Media\Services\PhotoStore;
use Modules\Shared\Providers\ModuleServiceProvider;

class MediaServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Media';
    }

    public function register(): void
    {
        parent::register();
        $this->app->singleton(PhotoStore::class);
        $this->app->singleton(Photos::class, fn ($app) => $app->make(PhotoStore::class));
        $this->app->singleton(ProfilePhotos::class, fn ($app) => $app->make(PhotoStore::class));
    }

    public function boot(): void
    {
        parent::boot();
        Event::listen(AccountDeleting::class, ForgetPhoto::class);

        RateLimiter::for('photo-uploads', fn (Request $request) => Limit::perHour((int) config('media.uploads_per_hour'))
            ->by('user:'.$request->user()?->getAuthIdentifier()));
    }
}
