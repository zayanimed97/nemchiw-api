<?php

namespace Modules\Push;

use Illuminate\Support\Facades\Event;
use Modules\Chat\Events\MessagePosted;
use Modules\Push\Listeners\SendPushes;
use Modules\Shared\Events\ApplicationDecided;
use Modules\Shared\Events\ReportResolved;
use Modules\Shared\Events\RequestChanged;
use Modules\Shared\Events\TripReminderDue;
use Modules\Shared\Places\PlaceModerated;
use Modules\Shared\Providers\ModuleServiceProvider;

class PushServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Push';
    }

    public function boot(): void
    {
        parent::boot();
        $this->loadTranslationsFrom($this->modulePath('lang'), 'push');
        Event::listen(MessagePosted::class, [SendPushes::class, 'message']);
        Event::listen(RequestChanged::class, [SendPushes::class, 'requestChanged']);
        Event::listen(TripReminderDue::class, [SendPushes::class, 'reminder']);
        Event::listen(ApplicationDecided::class, [SendPushes::class, 'application']);
        Event::listen(PlaceModerated::class, [SendPushes::class, 'place']);
        Event::listen(ReportResolved::class, [SendPushes::class, 'report']);
    }
}
