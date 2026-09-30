<?php

namespace Modules\Identity\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Models\User;
use Modules\Shared\Support\Iso;

/**
 * What other people see (PublicProfile in src/api/types.ts). Built field by
 * field on purpose: nothing private can slip in by adding a column.
 *
 * @mixin User
 */
final class PublicProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'level' => $this->level,
            'skills' => $this->skills ?? [],
            'bio' => $this->bio,
            'homeArea' => $this->home_governorate === null
                ? null
                : ['governorate' => $this->home_governorate, 'city' => $this->home_city],
            'car' => $this->car_seats === null ? null : ['seats' => $this->car_seats],
            'joinedAt' => Iso::format($this->created_at),
            'campsCount' => 0,
            'age' => $this->birth_date?->age,
            'photo' => null, // plan 3: { url, verified } only when verified
        ];
    }
}
