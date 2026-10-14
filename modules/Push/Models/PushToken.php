<?php

namespace Modules\Push\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $user_id
 * @property string $token
 * @property string $platform
 * @property string $locale
 */
class PushToken extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'token', 'platform', 'locale', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
