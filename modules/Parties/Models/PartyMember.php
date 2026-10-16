<?php

namespace Modules\Parties\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $party_id
 * @property string|null $user_id
 * @property string|null $contact_hash
 * @property string|null $contact_masked
 * @property string $state
 * @property Carbon|null $invited_at
 * @property Carbon|null $answered_at
 * @property Carbon|null $reminded_at
 */
class PartyMember extends Model
{
    use HasUlids;

    public const INVITED = 'invited';

    public const IN = 'in';

    public const DECLINED = 'declined';

    public const LEFT = 'left';

    public const REMOVED = 'removed';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['party_id', 'user_id', 'contact_hash', 'contact_masked', 'state', 'invited_at', 'answered_at', 'reminded_at'];

    protected function casts(): array
    {
        return ['invited_at' => 'datetime', 'answered_at' => 'datetime', 'reminded_at' => 'datetime'];
    }
}
