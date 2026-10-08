<?php

namespace Modules\Spots\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Media\Contracts\PlacePhotos;
use Modules\Shared\Places\PlaceFacts;
use Modules\Shared\Places\PlaceKind;

final class Spot extends Model
{
    use Prunable, SoftDeletes;

    public const TYPES = ['beach', 'forest', 'desert', 'mountain'];

    public const ACCESS = ['car', 'fourByFour'];

    public const COVERAGE = PlaceFacts::COVERAGE;

    public const PERMIT = PlaceFacts::PERMIT;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'name', 'description', 'latitude', 'longitude', 'governorate',
        'type', 'access', 'water', 'coverage', 'permit', 'status', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'water' => 'boolean',
        ];
    }

    /** Tombstones past the sync window go for good (`model:prune`, daily). */
    public function prunable(): Builder
    {
        return self::onlyTrashed()->where('deleted_at', '<', now()->subDays((int) config('shared.places.tombstone_days')));
    }

    /** Photos go with the place when its tombstone is pruned. */
    protected function pruning(): void
    {
        app(PlacePhotos::class)->forgetPlace(PlaceKind::SPOT, $this->id);
    }
}
