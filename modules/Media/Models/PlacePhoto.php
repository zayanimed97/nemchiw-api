<?php

namespace Modules\Media\Models;

use Illuminate\Database\Eloquent\Model;

final class PlacePhoto extends Model
{
    protected $fillable = ['kind', 'place_id', 'hash', 'position'];
}
