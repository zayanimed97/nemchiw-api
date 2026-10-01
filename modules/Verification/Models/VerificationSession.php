<?php

namespace Modules\Verification\Models;

use Illuminate\Database\Eloquent\Model;

/** A Didit session we started, tied to the photo it checks. */
final class VerificationSession extends Model
{
    protected $primaryKey = 'session_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.v';
}
