<?php

namespace Modules\Centres\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Centres\Factories\CentreFactory;
use Modules\Media\Contracts\PlacePhotos;
use Modules\Shared\Places\PlaceKind;

/** A partner camping centre, created in the admin. Its phone is a business number, public by design. */
final class Centre extends Model
{
    /** @use HasFactory<CentreFactory> */
    use HasFactory, Prunable, SoftDeletes;

    public const AMENITIES = ['water', 'showers', 'toilets', 'electricity', 'restaurant', 'tentRental', 'parking', 'shade'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'name', 'description', 'latitude', 'longitude', 'governorate', 'amenities',
        'price_from_tnd', 'phone', 'link', 'open_months', 'status', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'amenities' => 'array',
            'open_months' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'price_from_tnd' => 'float',
        ];
    }

    protected static function newFactory(): CentreFactory
    {
        return CentreFactory::new();
    }

    public function prunable(): Builder
    {
        return self::onlyTrashed()->where('deleted_at', '<', now()->subDays((int) config('shared.places.tombstone_days')));
    }

    protected function pruning(): void
    {
        app(PlacePhotos::class)->forgetPlace(PlaceKind::CENTRE, $this->id);
    }
}
