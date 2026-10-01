<?php

namespace Modules\SocialAuth\Services;

use Modules\Identity\Contracts\LinkedProviders;
use Modules\SocialAuth\Models\SocialIdentity;

final class EloquentLinkedProviders implements LinkedProviders
{
    public function for(string $userId): array
    {
        return SocialIdentity::query()->where('user_id', $userId)->orderBy('id')->pluck('provider')->unique()->values()->all();
    }
}
