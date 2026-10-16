<?php

use App\Providers\AppServiceProvider;
use Modules\Admin\AdminServiceProvider;
use Modules\Admin\Filament\AdminPanelProvider;
use Modules\Centres\CentresServiceProvider;
use Modules\Chat\ChatServiceProvider;
use Modules\Identity\IdentityServiceProvider;
use Modules\Media\MediaServiceProvider;
use Modules\Organisers\OrganisersServiceProvider;
use Modules\Otp\OtpServiceProvider;
use Modules\Parties\PartiesServiceProvider;
use Modules\Push\PushServiceProvider;
use Modules\Shared\SharedServiceProvider;
use Modules\SocialAuth\SocialAuthServiceProvider;
use Modules\Spots\SpotsServiceProvider;
use Modules\Trails\TrailsServiceProvider;
use Modules\Trips\TripsServiceProvider;
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
    OrganisersServiceProvider::class,
    TripsServiceProvider::class,
    PartiesServiceProvider::class,
    ChatServiceProvider::class,
    PushServiceProvider::class,
    VerificationServiceProvider::class,
    AdminServiceProvider::class,
    AdminPanelProvider::class,
];
