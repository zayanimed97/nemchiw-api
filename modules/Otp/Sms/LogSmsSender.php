<?php

namespace Modules\Otp\Sms;

use Illuminate\Support\Facades\Log;
use Modules\Otp\Contracts\SmsSender;
use RuntimeException;

/** Development driver: the code shows up in storage/logs. Never in production. */
final class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SMS_DRIVER=log is not allowed in production');
        }

        Log::info("SMS to {$phone}: {$message}");
    }
}
