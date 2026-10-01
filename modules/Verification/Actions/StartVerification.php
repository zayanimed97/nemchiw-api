<?php

namespace Modules\Verification\Actions;

use Illuminate\Support\Facades\RateLimiter;
use Modules\Media\Contracts\Photos;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Verification\Contracts\IdentityVerifier;
use Modules\Verification\Models\VerificationSession;

final class StartVerification
{
    public function __construct(
        private readonly Photos $photos,
        private readonly IdentityVerifier $verifier,
    ) {}

    /**
     * Creating the session does not change the photo status: Didit's webhook does,
     * once the person has actually submitted the check.
     *
     * @return array{sessionToken: string}
     */
    public function __invoke(string $userId, string $language): array
    {
        $photo = $this->photos->current($userId);
        if ($photo === null) {
            throw new ApiException(ApiErrorCode::Validation, 'Upload a photo first');
        }
        if ($photo['status'] === 'verified') {
            throw new ApiException(ApiErrorCode::Validation, 'This photo is already verified');
        }

        // Each check costs money. Slots are taken before calling Didit (so parallel
        // requests cannot all slip under the cap) and given back if the call fails.
        $budgets = [
            "verification:{$userId}" => (int) config('verification.sessions_per_day'),
            'verification:global' => (int) config('verification.global_sessions_per_day'),
        ];
        $taken = [];
        try {
            foreach ($budgets as $key => $max) {
                $taken[] = $key;
                if (RateLimiter::increment($key, 86_400) > $max) {
                    throw new ApiException(ApiErrorCode::RateLimited, 'Too many checks today', RateLimiter::availableIn($key));
                }
            }

            $session = $this->verifier->createSession($userId, $this->photos->portrait($photo['id']), $language);
        } catch (\Throwable $e) {
            foreach ($taken as $key) {
                RateLimiter::decrement($key, 86_400);
            }

            throw $e;
        }
        (new VerificationSession)->forceFill([
            'session_id' => $session['sessionId'],
            'user_id' => $userId,
            'photo_id' => $photo['id'],
        ])->save();

        return ['sessionToken' => $session['sessionToken']];
    }
}
