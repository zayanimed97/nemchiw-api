<?php

namespace Modules\Verification;

use Modules\Shared\Providers\ModuleServiceProvider;
use Modules\Verification\Contracts\IdentityVerifier;
use Modules\Verification\Didit\DiditVerifier;

class VerificationServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Verification';
    }

    public function register(): void
    {
        parent::register();
        $this->app->bind(IdentityVerifier::class, fn () => new DiditVerifier((array) config('verification.didit')));
    }
}
