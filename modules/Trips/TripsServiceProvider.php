<?php

namespace Modules\Trips;

use Modules\Shared\Providers\ModuleServiceProvider;

class TripsServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Trips';
    }
}
