<?php

namespace Modules\Trips\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TripRequest extends Model
{
    use HasUlids;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['trip_id', 'leader_id', 'note', 'status', 'accepted_at', 'joined_at', 'ended_at', 'ended_by', 'withdrawn_from'];

    protected $hidden = ['code_secret'];

    protected function casts(): array
    {
        return [
            'code_secret' => 'encrypted',
            'accepted_at' => 'datetime',
            'joined_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return HasMany<TripRequestMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(TripRequestMember::class, 'request_id');
    }
}
