<?php

namespace Modules\Otp\Testing;

use Modules\Otp\Contracts\OtpSender;

final class FakeOtpSender implements OtpSender
{
    /** @var list<array{phone: string, code: string, locale: string}> */
    public array $sent = [];

    public function send(string $phone, string $code, string $locale): void
    {
        $this->sent[] = ['phone' => $phone, 'code' => $code, 'locale' => $locale];
    }

    public function lastCode(): ?string
    {
        $last = end($this->sent);

        return $last ? $last['code'] : null;
    }
}
