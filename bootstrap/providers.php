<?php

use App\Providers\AppServiceProvider;
use Modules\Admin\AdminServiceProvider;
use Modules\Admin\Filament\AdminPanelProvider;
use Modules\Centres\CentresServiceProvider;
use Modules\Identity\IdentityServiceProvider;
use Modules\Media\MediaServiceProvider;
use Modules\Otp\OtpServiceProvider;
use Modules\Shared\SharedServiceProvider;
use Modules\SocialAuth\SocialAuthServiceProvider;
use Modules\Spots\SpotsServiceProvider;
use Modules\Trails\TrailsServiceProvider;
use Modules\Verification\VerificationServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    SpotsServiceProvider::class,
    TrailsServiceProvider::class,
    CentresServiceProvider::class,
    IdentityServiceProvider::class,
    OtpServiceProvider::class,
    SocialAuthServiceProvider::class,
    MediaServiceProvider::class,
    VerificationServiceProvider::class,
    AdminServiceProvider::class,
    AdminPanelProvider::class,
];
