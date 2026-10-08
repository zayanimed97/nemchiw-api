<?php

namespace Modules\Centres\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Centres\Models\Centre;
use Modules\Shared\Support\Iso;

/** @mixin Centre */
final class CentreResource extends JsonResource
{
    /** @param list<string> $photos */
    public function __construct(Centre $resource, private readonly array $photos = [])
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
            'amenities' => array_values($this->amenities),
            'priceFromTnd' => $this->price_from_tnd,
            'phone' => $this->phone,
            'link' => $this->link,
            'openMonths' => array_map('intval', array_values($this->open_months)),
            'photos' => $this->photos,
            'updatedAt' => Iso::format($this->updated_at),
        ];
    }
}
