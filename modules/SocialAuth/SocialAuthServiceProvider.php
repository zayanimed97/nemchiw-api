<?php

namespace Modules\SocialAuth;

use Modules\Shared\Providers\ModuleServiceProvider;

class SocialAuthServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'SocialAuth';
    }
}
