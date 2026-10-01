<?php

namespace Modules\Identity\Contracts;

use Modules\Shared\Errors\ApiException;

/** How other modules act on accounts. Arrays are the app's wire shapes. */
interface Accounts
{
    /**
     * Signs in with a phone the caller has already proven (OTP), creating the
     * account on first use.
     *
     * @return array{token: string, profile: array<string, mixed>, isNew: bool}
     */
    public function signInWithPhone(string $phone): array;

    /**
     * Creates an empty account with optional profile hints (already cleaned by the caller).
     *
     * @return string the new user id
     */
    public function createAccount(?string $firstName, ?string $lastName, ?string $email): string;

    /**
     * Signs an existing account in: a fresh token plus the owner profile.
     *
     * @return array{token: string, profile: array<string, mixed>, isNew: bool}
     */
    public function authResponse(string $userId, bool $isNew): array;

    /**
     * The owner's view of an account (Profile in the app's contract).
     *
     * @return array<string, mixed>
     */
    public function profile(string $userId): array;

    /** The id of the account holding this phone, if any. */
    public function ownerOfPhone(string $phone): ?string;

    /**
     * Sets a proven phone on an account.
     *
     * @return array<string, mixed> the updated profile
     *
     * @throws ApiException phone_taken
     */
    public function attachPhone(string $userId, string $phone): array;
}
