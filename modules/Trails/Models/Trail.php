<?php

namespace Modules\Trails\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Media\Contracts\PlacePhotos;
use Modules\Shared\Places\PlaceKind;
use Modules\Trails\Factories\TrailFactory;

final class Trail extends Model
{
    /** @use HasFactory<TrailFactory> */
    use HasFactory, Prunable, SoftDeletes;

    public const DIFFICULTY = ['easy', 'moderate', 'hard', 'expert'];

    public const SHAPES = ['loop', 'outAndBack', 'oneWay'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'name', 'description', 'latitude', 'longitude', 'governorate', 'distance_km',
        'elevation_gain_m', 'duration_min', 'difficulty', 'shape', 'water_on_route',
        'coverage', 'permit', 'status', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'distance_km' => 'float',
            'elevation_gain_m' => 'integer',
            'duration_min' => 'integer',
            'water_on_route' => 'boolean',
        ];
    }

    protected static function newFactory(): TrailFactory
    {
        return TrailFactory::new();
    }

    public function prunable(): Builder
    {
        return self::onlyTrashed()->where('deleted_at', '<', now()->subDays((int) config('shared.places.tombstone_days')));
    }

    protected function pruning(): void
    {
        app(PlacePhotos::class)->forgetPlace(PlaceKind::TRAIL, $this->id);
    }
}
