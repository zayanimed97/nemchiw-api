<?php

namespace Modules\Otp\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Otp\Models\OtpChallenge;
use Modules\Otp\Support\OtpCode;
use Modules\Otp\Support\PhoneLock;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

/**
 * Checks a code against a challenge. Single use, at most `otp.max_attempts`
 * tries. The attempt is committed before any error is thrown, so failures count.
 */
final class ConsumeChallenge
{
    public function __invoke(string $challengeId, string $code, string $purpose, ?string $userId): OtpChallenge
    {
        [$error, $challenge] = DB::transaction(function () use ($challengeId, $code, $purpose, $userId): array {
            $challenge = OtpChallenge::query()
                ->whereKey($challengeId)
                ->where('purpose', $purpose)
                ->when($userId === null, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->where('user_id', $userId))
                ->lockForUpdate()
                ->first();

            if ($challenge === null || $challenge->consumed_at !== null || $challenge->expires_at->isPast()) {
                return [ApiErrorCode::CodeExpired, null];
            }
            if ($challenge->attempts >= (int) config('otp.max_attempts') || PhoneLock::lockedFor($challenge->phone) !== null) {
                return [ApiErrorCode::RateLimited, $challenge];
            }

            $challenge->attempts++;
            $matches = hash_equals($challenge->code_hash, OtpCode::hash($challenge->id, $code));
            if ($matches) {
                $challenge->consumed_at = now();
            } else {
                PhoneLock::recordFailure($challenge->phone);
            }
            $challenge->save();

            return [$matches ? null : ApiErrorCode::InvalidCode, $challenge];
        });

        return match ($error) {
            null => $challenge,
            ApiErrorCode::CodeExpired => throw new ApiException($error, 'Code expired'),
            ApiErrorCode::RateLimited => throw new ApiException(
                $error,
                'Too many attempts',
                PhoneLock::lockedFor($challenge->phone) ?? max(1, (int) ceil(now()->diffInSeconds($challenge->expires_at))),
            ),
            default => throw new ApiException($error, 'Wrong code'),
        };
    }
}
