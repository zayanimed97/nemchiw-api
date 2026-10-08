<?php

namespace Modules\Trails;

use Illuminate\Support\Facades\Event;
use Modules\Media\Events\PlacePhotosChanged;
use Modules\Shared\Places\PlaceKind;
use Modules\Shared\Providers\ModuleServiceProvider;
use Modules\Trails\Models\Trail;

class TrailsServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Trails';
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(function (PlacePhotosChanged $event) {
            if ($event->kind === PlaceKind::TRAIL) {
                Trail::withTrashed()->find($event->placeId)?->touch();
            }
        });
    }
}
