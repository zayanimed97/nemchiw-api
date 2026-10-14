<?php

namespace Modules\Chat\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $request_id
 * @property string|null $trip_id
 * @property string|null $camper_id
 * @property string|null $organiser_id
 * @property string $trip_title
 * @property string $trip_kind
 * @property Carbon $trip_starts_at
 * @property Carbon|null $last_message_at
 * @property Carbon|null $closes_at
 * @property Carbon $updated_at
 */
class Conversation extends Model
{
    use HasUlids;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['request_id', 'trip_id', 'camper_id', 'organiser_id', 'trip_title', 'trip_kind', 'trip_starts_at', 'last_message_at', 'closes_at'];

    protected function casts(): array
    {
        return ['trip_starts_at' => 'datetime', 'last_message_at' => 'datetime', 'closes_at' => 'datetime'];
    }

    public function isParticipant(string $userId): bool
    {
        return $userId === $this->camper_id || $userId === $this->organiser_id;
    }

    /** The other participant, null when they deleted their account. */
    public function otherThan(string $userId): ?string
    {
        return $userId === $this->camper_id ? $this->organiser_id : $this->camper_id;
    }

    public function isClosed(): bool
    {
        return $this->closes_at !== null && ! $this->closes_at->isFuture();
    }
}
