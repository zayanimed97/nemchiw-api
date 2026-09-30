<?php

namespace Modules\Otp\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Otp\Contracts\SmsSender;
use Modules\Otp\Models\OtpChallenge;
use Modules\Otp\Support\OtpCode;
use Modules\Otp\Support\OtpMessage;
use Modules\Otp\Support\PhoneLock;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

use function Illuminate\Support\defer;

final class SendOtp
{
    public function __construct(private readonly SmsSender $sms) {}

    /** @return array{challengeId: string, resendAfter: int, expiresIn: int} */
    public function __invoke(string $phone, string $purpose, ?string $userId, string $locale): array
    {
        // One send per phone at a time, so two fast taps cannot both pass the checks.
        $lock = Cache::lock("otp:send:{$phone}", 10);
        if (! $lock->get()) {
            throw new ApiException(ApiErrorCode::RateLimited, 'Wait before requesting another code', 1);
        }

        try {
            $this->guardLimits($phone, $purpose);

            $now = now();
            // A resend must not kill the code someone is typing (anyone can request a
            // code for any phone), but a few live codes at most keeps guessing odds low.
            $keep = OtpChallenge::query()->where('phone', $phone)->where('purpose', $purpose)
                ->whereNull('consumed_at')->where('expires_at', '>', $now)
                ->latest('created_at')->limit((int) config('otp.live_codes') - 1)->pluck('id');
            OtpChallenge::query()->where('phone', $phone)->where('purpose', $purpose)
                ->whereNull('consumed_at')->whereNotIn('id', $keep)->update(['consumed_at' => $now]);

            $code = OtpCode::generate();
            $challenge = new OtpChallenge;
            $challenge->id = $challenge->newUniqueId();
            $challenge->forceFill([
                'phone' => $phone,
                'purpose' => $purpose,
                'user_id' => $userId,
                'code_hash' => OtpCode::hash($challenge->id, $code),
                'expires_at' => $now->addSeconds((int) config('otp.code_ttl')),
                'resend_after' => $now->addSeconds((int) config('otp.resend_after')),
            ])->save();

            RateLimiter::hit("otp:phone:{$phone}", 3600);
            $spent = RateLimiter::hit('otp:global', 3600);
            if ($spent === (int) ceil(config('otp.limits.global_per_hour') / 2)) {
                Log::warning('OTP: half of the hourly SMS budget is spent; check for SMS pumping');
            }
            // After the response is flushed, never through the queue: the code is never
            // stored in plain text, and the cron-driven worker on shared hosting would
            // delay it by up to a minute.
            $message = OtpMessage::for($locale, $code);
            defer(fn () => $this->sms->send($phone, $message));

            return [
                'challengeId' => $challenge->id,
                'resendAfter' => (int) config('otp.resend_after'),
                'expiresIn' => (int) config('otp.code_ttl'),
            ];
        } finally {
            $lock->release();
        }
    }

    private function guardLimits(string $phone, string $purpose): void
    {
        $locked = PhoneLock::lockedFor($phone);
        if ($locked !== null) {
            throw new ApiException(ApiErrorCode::RateLimited, 'Too many wrong codes', $locked);
        }

        $latest = OtpChallenge::query()->where('phone', $phone)->where('purpose', $purpose)
            ->latest('created_at')->first();
        if ($latest !== null && $latest->resend_after->isFuture()) {
            $wait = max(1, (int) ceil(now()->diffInSeconds($latest->resend_after)));
            throw new ApiException(ApiErrorCode::RateLimited, 'Wait before requesting another code', $wait);
        }

        foreach ([
            "otp:phone:{$phone}" => (int) config('otp.limits.per_phone_per_hour'),
            'otp:global' => (int) config('otp.limits.global_per_hour'),
        ] as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw new ApiException(ApiErrorCode::RateLimited, 'Too many codes requested', RateLimiter::availableIn($key));
            }
        }
    }
}
