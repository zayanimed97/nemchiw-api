<?php

namespace Modules\Identity\Services;

use Illuminate\Http\Request;
use Modules\Identity\Contracts\People;
use Modules\Identity\Contracts\ProfilePhotos;
use Modules\Identity\Http\PublicProfileResource;
use Modules\Identity\Models\User;

final class EloquentPeople implements People
{
    public const MIN_TRIP_AGE = 18;

    public function __construct(private readonly ProfilePhotos $photos) {}

    public function publicProfiles(array $userIds): array
    {
        $request = Request::create('/');

        return User::query()->whereIn('id', array_values(array_unique($userIds)))->get()
            ->mapWithKeys(fn (User $user) => [$user->id => PublicProfileResource::make($user)->resolve($request)])
            ->all();
    }

    public function participantDetails(string $userId): array
    {
        $user = User::query()->findOrFail($userId);

        return [
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'gender' => $user->gender,
            'age' => $user->birth_date?->age,
            'phone' => $user->phone,
            'emergencyContact' => $user->emergency_contact,
            'medicalInfo' => $user->medical_info,
        ];
    }

    public function tripReadiness(string $userId): array
    {
        $user = User::query()->findOrFail($userId);
        $verifiedPhoto = $this->photos->publicView($userId) !== null;
        $ready = filled(trim((string) $user->first_name))
            && filled(trim((string) $user->last_name))
            && $user->gender !== null
            && $user->birth_date !== null && $user->birth_date->age >= self::MIN_TRIP_AGE
            && $user->phone !== null && $user->phone_verified_at !== null
            && $user->emergency_contact !== null
            && $verifiedPhoto;

        return ['ready' => $ready, 'gender' => $user->gender, 'verifiedPhoto' => $verifiedPhoto];
    }

    public function phone(string $userId): ?string
    {
        return User::query()->whereKey($userId)->value('phone');
    }
}
