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
