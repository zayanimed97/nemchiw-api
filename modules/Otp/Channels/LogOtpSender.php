<?php

namespace Modules\Otp\Channels;

use Illuminate\Support\Facades\Log;
use Modules\Otp\Contracts\OtpSender;
use Modules\Otp\Support\OtpMessage;
use RuntimeException;

/** Development channel: the code shows up in storage/logs. Never in production. */
final class LogOtpSender implements OtpSender
{
    public function send(string $phone, string $code, string $locale): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('OTP_CHANNEL=log is not allowed in production');
        }

        Log::info("Code for {$phone}: ".OtpMessage::for($locale, $code));
    }
}
