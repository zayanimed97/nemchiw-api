<?php

namespace Modules\Chat\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $conversation_id
 * @property string $user_id
 * @property string|null $last_read_id
 * @property Carbon|null $viewing_until
 */
class ConversationRead extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $fillable = ['conversation_id', 'user_id', 'last_read_id', 'viewing_until'];

    protected function casts(): array
    {
        return ['viewing_until' => 'datetime'];
    }
}
