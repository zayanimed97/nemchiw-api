<?php

namespace Modules\Chat\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    /** Camper (the current leader), organiser, or a party member who reads. */
    public function isParticipant(string $userId): bool
    {
        return $this->canWrite($userId) || DB::table('conversation_readers')
            ->where('conversation_id', $this->id)->where('user_id', $userId)->exists();
    }

    /** Only the leader (camper) and the organiser write. */
    public function canWrite(string $userId): bool
    {
        return $userId === $this->camper_id || $userId === $this->organiser_id;
    }

    /** @return list<string> */
    public function readerIds(): array
    {
        return DB::table('conversation_readers')->where('conversation_id', $this->id)->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (string) $id)->all();
    }

    /** Everyone who sees the conversation, writers first.
     *
     * @return list<string>
     */
    public function participantIds(): array
    {
        return array_values(array_filter([$this->camper_id, $this->organiser_id, ...$this->readerIds()]));
    }

    public function isClosed(): bool
    {
        return $this->closes_at !== null && ! $this->closes_at->isFuture();
    }
}
