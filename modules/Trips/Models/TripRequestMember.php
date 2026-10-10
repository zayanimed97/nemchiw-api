<?php

namespace Modules\Trips\Models;

use Illuminate\Database\Eloquent\Model;

final class TripRequestMember extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['request_id', 'user_id', 'scanned_at'];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }
}
