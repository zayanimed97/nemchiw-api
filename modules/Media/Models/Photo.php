<?php

namespace Modules\Media\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class Photo extends Model
{
    use HasUlids;

    public const STATUSES = ['unverified', 'pending', 'verified', 'rejected'];

    protected $hidden = ['path'];
}
