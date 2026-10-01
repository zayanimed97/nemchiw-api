<?php

namespace Modules\SocialAuth\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\SocialAuth\Models\SocialIdentity;

final class SocialSignInRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(SocialIdentity::PROVIDERS)],
            'token' => ['required', 'string', 'max:8192'],
            'nonce' => ['nullable', 'string', 'max:128'],
            'hint' => ['sometimes', 'nullable', 'array:firstName,lastName,email'],
            'hint.firstName' => ['nullable', 'string', 'max:200'],
            'hint.lastName' => ['nullable', 'string', 'max:200'],
            'hint.email' => ['nullable', 'string', 'max:254'],
        ];
    }
}
