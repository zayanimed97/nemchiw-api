<?php

namespace Modules\Otp\Contracts;

interface SmsSender
{
    public function send(string $phone, string $message): void;
}
