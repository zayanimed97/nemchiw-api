<?php

namespace Modules\Organisers\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrganiserDocument extends Model
{
    use HasUlids;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['application_id', 'type', 'path', 'mime', 'size'];

    /** @return BelongsTo<OrganiserApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(OrganiserApplication::class, 'application_id');
    }
}
