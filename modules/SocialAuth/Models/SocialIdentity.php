<?php

namespace Modules\SocialAuth\Models;

use Illuminate\Database\Eloquent\Model;

/** One provider account (provider + its user id) linked to one of our users. */
final class SocialIdentity extends Model
{
    public const PROVIDERS = ['google', 'apple', 'facebook'];
}
