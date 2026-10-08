<?php

namespace Modules\Trails\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Support\Iso;
use Modules\Trails\Models\Trail;

/** @mixin Trail */
final class TrailResource extends JsonResource
{
    /** @param list<string> $photos */
    public function __construct(Trail $resource, private readonly array $photos = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'governorate' => $this->governorate,
            'distanceKm' => $this->distance_km,
            'elevationGainM' => $this->elevation_gain_m,
            'durationMin' => $this->duration_min,
            'difficulty' => $this->difficulty,
            'shape' => $this->shape,
            'waterOnRoute' => $this->water_on_route,
            'coverage' => $this->coverage,
            'permit' => $this->permit,
            'photos' => $this->photos,
            'updatedAt' => Iso::format($this->updated_at),
        ];
    }
}
