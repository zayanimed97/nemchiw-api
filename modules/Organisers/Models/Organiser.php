<?php

namespace Modules\Organisers\Models;

use Illuminate\Database\Eloquent\Model;

final class Organiser extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['user_id', 'certified_at', 'public_phone'];

    protected function casts(): array
    {
        return ['certified_at' => 'datetime'];
    }
}
