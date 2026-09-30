<?php

namespace Modules\Otp\Actions;

use Modules\Otp\Models\OtpChallenge;

final class PruneChallenges
{
    public function __invoke(): int
    {
        return OtpChallenge::query()->where('created_at', '<', now()->subDay())->delete();
    }
}
