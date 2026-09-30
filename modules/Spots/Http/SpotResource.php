<?php

namespace Modules\Spots\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Support\Iso;
use Modules\Spots\Models\Spot;

/** @mixin Spot */
final class SpotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'governorate' => $this->governorate,
            'type' => $this->type,
            'access' => $this->access,
            'water' => $this->water,
            'coverage' => $this->coverage,
            'permit' => $this->permit,
            'updatedAt' => Iso::format($this->updated_at),
        ];
    }
}
