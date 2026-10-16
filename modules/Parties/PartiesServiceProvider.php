<?php

namespace Modules\Parties;

use Illuminate\Support\Facades\Event;
use Modules\Identity\Events\AccountDeleting;
use Modules\Parties\Console\EndIdlePartiesCommand;
use Modules\Parties\Contracts\PartyRoster;
use Modules\Parties\Listeners\AttachPlaceholders;
use Modules\Parties\Listeners\FollowPartyRequest;
use Modules\Parties\Listeners\LeaveOnAccountDeleting;
use Modules\Parties\Services\EloquentPartyRoster;
use Modules\Shared\Events\ContactConfirmed;
use Modules\Shared\Events\RequestChanged;
use Modules\Shared\Events\TripFinished;
use Modules\Shared\Providers\ModuleServiceProvider;

class PartiesServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Parties';
    }

    public function register(): void
    {
        parent::register();
        $this->app->singleton(PartyRoster::class, EloquentPartyRoster::class);
    }

    public function boot(): void
    {
        parent::boot();
        Event::listen(ContactConfirmed::class, AttachPlaceholders::class);
        Event::listen(AccountDeleting::class, LeaveOnAccountDeleting::class);
        Event::listen(RequestChanged::class, [FollowPartyRequest::class, 'requestChanged']);
        Event::listen(TripFinished::class, [FollowPartyRequest::class, 'tripFinished']);

        if ($this->app->runningInConsole()) {
            $this->commands([EndIdlePartiesCommand::class]);
        }
    }
}
