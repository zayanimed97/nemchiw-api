<?php

namespace Modules\SocialAuth\Listeners;

use Modules\Identity\Events\AccountDeleting;
use Modules\SocialAuth\Models\SocialIdentity;

final class ForgetIdentities
{
    public function handle(AccountDeleting $event): void
    {
        SocialIdentity::query()->where('user_id', $event->userId)->delete();
    }
}
