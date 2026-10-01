<?php

namespace Modules\Identity\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Identity\Contracts\Accounts;
use Modules\Identity\Http\ProfileResource;
use Modules\Identity\Models\User;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

final class EloquentAccounts implements Accounts
{
    public function signInWithPhone(string $phone): array
    {
        $user = User::query()->where('phone', $phone)->first();
        $isNew = false;

        if ($user === null) {
            try {
                $user = new User;
                $user->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();
                $isNew = true;
            } catch (UniqueConstraintViolationException) {
                // Two verifies for the same new phone raced; the other one created it.
                $user = User::query()->where('phone', $phone)->firstOrFail();
            }
        }

        return $this->respond($user, $isNew);
    }

    public function createAccount(?string $firstName, ?string $lastName, ?string $email): string
    {
        $user = new User;
        $user->forceFill(['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email])->save();

        return $user->id;
    }

    public function profile(string $userId): array
    {
        return ProfileResource::make(User::query()->findOrFail($userId))->resolve();
    }

    public function authResponse(string $userId, bool $isNew): array
    {
        return $this->respond(User::query()->findOrFail($userId), $isNew);
    }

    public function ownerOfPhone(string $phone): ?string
    {
        return User::query()->where('phone', $phone)->value('id');
    }

    public function attachPhone(string $userId, string $phone): array
    {
        $user = User::query()->findOrFail($userId);
        $owner = $this->ownerOfPhone($phone);
        if ($owner !== null && $owner !== $userId) {
            throw new ApiException(ApiErrorCode::PhoneTaken, 'Phone in use');
        }

        try {
            $user->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();
        } catch (UniqueConstraintViolationException) {
            throw new ApiException(ApiErrorCode::PhoneTaken, 'Phone in use');
        }

        return ProfileResource::make($user)->resolve();
    }

    /** @return array{token: string, profile: array<string, mixed>, isNew: bool} */
    private function respond(User $user, bool $isNew): array
    {
        return ['token' => $this->issueToken($user), 'profile' => ProfileResource::make($user)->resolve(), 'isNew' => $isNew];
    }

    private function issueToken(User $user): string
    {
        $token = $user->createToken('app', ['*'], now()->addDays((int) config('identity.token_days')))->plainTextToken;

        $keep = $user->tokens()->latest('id')->limit((int) config('identity.max_tokens'))->pluck('id');
        $user->tokens()->whereNotIn('id', $keep)->delete();

        return $token;
    }
}
