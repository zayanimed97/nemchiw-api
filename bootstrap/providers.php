<?php

use App\Providers\AppServiceProvider;
use Modules\Identity\IdentityServiceProvider;
use Modules\Media\MediaServiceProvider;
use Modules\Otp\OtpServiceProvider;
use Modules\Shared\SharedServiceProvider;
use Modules\SocialAuth\SocialAuthServiceProvider;
use Modules\Spots\SpotsServiceProvider;
use Modules\Verification\VerificationServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    SpotsServiceProvider::class,
    IdentityServiceProvider::class,
    OtpServiceProvider::class,
    SocialAuthServiceProvider::class,
    MediaServiceProvider::class,
    VerificationServiceProvider::class,
];
