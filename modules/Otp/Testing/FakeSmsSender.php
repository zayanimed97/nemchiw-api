<?php

namespace Modules\Otp\Testing;

use Modules\Otp\Contracts\SmsSender;

final class FakeSmsSender implements SmsSender
{
    /** @var list<array{phone: string, message: string}> */
    public array $sent = [];

    public function send(string $phone, string $message): void
    {
        $this->sent[] = ['phone' => $phone, 'message' => $message];
    }

    public function lastCode(): ?string
    {
        $last = end($this->sent);

        return $last && preg_match('/\d{6}/', $last['message'], $m) ? $m[0] : null;
    }
}
