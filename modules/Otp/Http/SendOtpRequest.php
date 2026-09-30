<?php

namespace Modules\Otp\Http;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Shared\Support\TunisianPhone;

final class SendOtpRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:'.TunisianPhone::PATTERN],
            'locale' => ['required', 'in:ar,fr,en'],
        ];
    }
}
