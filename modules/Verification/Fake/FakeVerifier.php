<?php

namespace Modules\Verification\Fake;

use Illuminate\Support\Str;
use Modules\Media\Contracts\Photos;
use Modules\Verification\Contracts\IdentityVerifier;

/**
 * Local development without Didit (the simulator has no camera, and no webhook reaches a
 * laptop). The "mock-" token makes the app skip the Didit screens, and the decision is
 * taken at once instead of arriving by webhook. Bound only when APP_ENV=local and no key.
 */
final class FakeVerifier implements IdentityVerifier
{
    public function __construct(
        private readonly Photos $photos,
        private readonly string $outcome,
    ) {}

    public function createSession(string $userId, string $portraitJpeg, string $language): array
    {
        $photo = $this->photos->current($userId);
        if ($photo !== null) {
            $this->photos->setStatus($photo['id'], $this->outcome === 'rejected' ? 'rejected' : 'verified');
        }
        $id = 'mock-'.Str::lower((string) Str::ulid());

        return ['sessionId' => $id, 'sessionToken' => $id];
    }

    public function deleteSession(string $sessionId): void {}
}
