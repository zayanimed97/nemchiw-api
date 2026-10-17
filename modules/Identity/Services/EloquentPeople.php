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
        return $this->detailsOf(User::query()->findOrFail($userId));
    }

    public function participantDetailsFor(array $userIds): array
    {
        return User::query()->whereIn('id', array_values(array_unique($userIds)))->get()
            ->mapWithKeys(fn (User $user) => [$user->id => $this->detailsOf($user)])
            ->all();
    }

    /** @return array<string, mixed> */
    private function detailsOf(User $user): array
    {
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

    public function readiness(string $userId): array
    {
        $user = User::query()->findOrFail($userId);
        $verifiedPhoto = $this->photos->publicView($userId) !== null;
        $tooYoung = $user->birth_date !== null && $user->birth_date->age < self::MIN_TRIP_AGE;

        $missing = array_keys(array_filter([
            'firstName' => blank(trim((string) $user->first_name)),
            'phone' => $user->phone === null || $user->phone_verified_at === null,
            'lastName' => blank(trim((string) $user->last_name)),
            'gender' => $user->gender === null,
            'birthDate' => $user->birth_date === null,
            'photo' => ! $verifiedPhoto,
            'emergencyContact' => $user->emergency_contact === null,
        ]));

        return [
            'ready' => $missing === [] && ! $tooYoung,
            'missing' => $missing,
            'tooYoung' => $tooYoung,
            'gender' => $user->gender,
            'verifiedPhoto' => $verifiedPhoto,
        ];
    }

    public function tripReadiness(string $userId): array
    {
        $readiness = $this->readiness($userId);

        return [
            'ready' => $readiness['ready'],
            'gender' => $readiness['gender'],
            'verifiedPhoto' => $readiness['verifiedPhoto'],
        ];
    }

    public function findByContact(string $normalised): ?string
    {
        return User::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('phone', $normalised)->whereNotNull('phone_verified_at'))
                ->orWhere('confirmed_email', $normalised))
            ->value('id');
    }

    public function phone(string $userId): ?string
    {
        return User::query()->whereKey($userId)->value('phone');
    }
}
