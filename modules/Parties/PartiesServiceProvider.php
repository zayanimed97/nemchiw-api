<?php

namespace Modules\Parties;

use Modules\Shared\Providers\ModuleServiceProvider;

class PartiesServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Parties';
    }
}
