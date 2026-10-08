<?php

namespace Modules\Spots;

use Illuminate\Support\Facades\Event;
use Modules\Media\Events\PlacePhotosChanged;
use Modules\Shared\Places\PlaceKind;
use Modules\Shared\Providers\ModuleServiceProvider;
use Modules\Spots\Console\ImportSpotsCommand;
use Modules\Spots\Models\Spot;

class SpotsServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Spots';
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(function (PlacePhotosChanged $event) {
            if ($event->kind === PlaceKind::SPOT) {
                Spot::withTrashed()->find($event->placeId)?->touch();
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ImportSpotsCommand::class]);
        }
    }
}
