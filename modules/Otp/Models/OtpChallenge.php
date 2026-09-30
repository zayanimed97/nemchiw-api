<?php

namespace Modules\Otp\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class OtpChallenge extends Model
{
    use HasUlids;

    public const SIGN_IN = 'signin';

    public const ATTACH = 'attach';

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'resend_after' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
