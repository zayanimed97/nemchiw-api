<?php

namespace Modules\Trips\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Trips\Support\RequestStatus;

/** No factory or User relation here: Identity's models are off limits (arch test). Test factories live in tests/Factories. */
final class Trip extends Model
{
    use HasUlids;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'organiser_id', 'kind', 'title', 'description', 'destination_kind', 'destination_id', 'destination',
        'meetup_url', 'meetup_at', 'starts_at', 'ends_at', 'seats', 'price_tnd', 'women_only', 'status', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'destination' => 'array',
            'meetup_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'seats' => 'integer',
            'price_tnd' => 'integer',
            'women_only' => 'boolean',
        ];
    }

    /** @return HasMany<TripRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(TripRequest::class);
    }

    /** People holding a seat: members of joined requests. */
    public function joinedCount(): int
    {
        return TripRequestMember::query()
            ->whereIn('request_id', $this->requests()->where('status', RequestStatus::JOINED)->select('id'))
            ->count();
    }
}
