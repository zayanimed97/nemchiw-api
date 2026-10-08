<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

final class AdminAction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['admin_id', 'action', 'target_kind', 'target_id', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }
}
