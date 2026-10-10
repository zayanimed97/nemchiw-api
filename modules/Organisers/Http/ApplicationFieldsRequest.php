<?php

namespace Modules\Organisers\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Shared\Support\Governorates;
use Modules\Shared\Support\TunisianPhone;

final class ApplicationFieldsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'regions' => ['required', 'array', 'min:1', 'max:24'],
            'regions.*' => ['string', 'distinct', Rule::in(Governorates::ALL)],
            'experienceYears' => ['required', 'integer', 'between:0,60'],
            'publicPhone' => ['nullable', 'string', 'regex:'.TunisianPhone::PATTERN],
        ];
    }
}
