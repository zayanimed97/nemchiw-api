<?php

namespace Modules\Parties\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $leader_id
 * @property string $state
 * @property string|null $request_id
 * @property Carbon|null $last_activity_at
 * @property Carbon $created_at
 */
class Party extends Model
{
    use HasUlids;

    public const FORMING = 'forming';

    public const ASKING = 'asking';

    public const GOING = 'going';

    public const OVER = 'over';

    protected $fillable = ['leader_id', 'state', 'request_id', 'last_activity_at'];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime'];
    }

    /** @return HasMany<PartyMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }

    /** The leader plus the `in` members: who the party is right now. @return list<string> */
    public function memberIds(): array
    {
        $in = $this->members()->where('state', PartyMember::IN)->whereNotNull('user_id')->pluck('user_id')->all();

        return array_values(array_unique([$this->leader_id, ...$in]));
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }
}
