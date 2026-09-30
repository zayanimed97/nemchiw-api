<?php

use App\Providers\AppServiceProvider;
use Modules\Identity\IdentityServiceProvider;
use Modules\Shared\SharedServiceProvider;
use Modules\Spots\SpotsServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    SpotsServiceProvider::class,
    IdentityServiceProvider::class,
];
