<?php

namespace Modules\Verification\Contracts;

/** The liveness + face-match provider (Didit). Failures throw ApiException(provider_unavailable). */
interface IdentityVerifier
{
    /** @return array{sessionId: string, sessionToken: string} */
    public function createSession(string $userId, string $portraitJpeg, string $language): array;

    /** Erases the session and its biometric data at the provider. */
    public function deleteSession(string $sessionId): void;
}
