<?php

namespace Modules\Identity\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Contracts\LinkedProviders;
use Modules\Identity\Models\User;
use Modules\Shared\Support\Iso;

/**
 * The owner's own view (Profile in src/api/types.ts). Never send this to anyone
 * but the account holder; other people get PublicProfileResource.
 *
 * @mixin User
 */
final class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'gender' => $this->gender,
            'birthDate' => $this->birth_date?->format('Y-m-d'),
            'phone' => $this->phone,
            'email' => $this->email,
            'photo' => null, // plan 3 (Media) fills this
            'level' => $this->level,
            'skills' => $this->skills ?? [],
            'bio' => $this->bio,
            'homeArea' => $this->home_governorate === null
                ? null
                : ['governorate' => $this->home_governorate, 'city' => $this->home_city],
            'car' => $this->car_seats === null ? null : ['seats' => $this->car_seats],
            'emergencyContact' => $this->emergency_contact,
            'providers' => app(LinkedProviders::class)->for($this->id),
            'joinedAt' => Iso::format($this->created_at),
            'campsCount' => 0, // trips do not exist yet
        ];
    }
}
