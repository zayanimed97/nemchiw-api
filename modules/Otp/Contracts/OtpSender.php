<?php

namespace Modules\Otp\Contracts;

/** Delivers a one-time code. Throws ApiException(provider_unavailable) when it cannot. */
interface OtpSender
{
    /** @param  string  $locale  ar, fr or en */
    public function send(string $phone, string $code, string $locale): void;
}
