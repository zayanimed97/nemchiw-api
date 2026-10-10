<?php

namespace Modules\Trips\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TripReport extends Model
{
    use HasUlids;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['request_id', 'reporter_id', 'reason', 'note', 'status', 'resolved_at', 'resolved_by'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<TripRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class, 'request_id');
    }
}
