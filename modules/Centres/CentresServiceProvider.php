<?php

namespace Modules\Centres;

use Illuminate\Support\Facades\Event;
use Modules\Centres\Models\Centre;
use Modules\Media\Events\PlacePhotosChanged;
use Modules\Shared\Places\PlaceKind;
use Modules\Shared\Providers\ModuleServiceProvider;

class CentresServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Centres';
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(function (PlacePhotosChanged $event) {
            if ($event->kind === PlaceKind::CENTRE) {
                Centre::withTrashed()->find($event->placeId)?->touch();
            }
        });
    }
}
