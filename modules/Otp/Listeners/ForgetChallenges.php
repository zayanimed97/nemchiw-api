<?php

namespace Modules\Otp\Listeners;

use Modules\Identity\Events\AccountDeleting;
use Modules\Otp\Models\OtpChallenge;

final class ForgetChallenges
{
    public function handle(AccountDeleting $event): void
    {
        OtpChallenge::query()
            ->where('user_id', $event->userId)
            ->when($event->phone !== null, fn ($q) => $q->orWhere('phone', $event->phone))
            ->delete();
    }
}
