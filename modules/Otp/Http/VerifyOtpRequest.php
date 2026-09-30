<?php

namespace Modules\Otp\Http;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyOtpRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'challengeId' => ['required', 'string', 'ulid'],
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
