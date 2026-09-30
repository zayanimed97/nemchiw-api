<?php

namespace Modules\Shared;

use Modules\Shared\Providers\ModuleServiceProvider;

class SharedServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Shared';
    }
}
