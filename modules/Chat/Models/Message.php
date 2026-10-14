<?php

namespace Modules\Chat\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string|null $sender_id
 * @property string|null $actor_id
 * @property string|null $client_id
 * @property string $kind
 * @property string|null $body
 * @property string|null $photo_hash
 * @property float|null $lat
 * @property float|null $lng
 * @property int|null $accuracy_m
 * @property string|null $system_key
 * @property array<string, string>|null $system_params
 * @property Carbon $created_at
 */
class Message extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['conversation_id', 'sender_id', 'actor_id', 'client_id', 'kind', 'body', 'photo_hash', 'lat', 'lng', 'accuracy_m', 'system_key', 'system_params'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'accuracy_m' => 'integer', 'system_params' => 'array', 'created_at' => 'datetime'];
    }
}
