<?php

namespace Modules\Identity\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Profile\ProfileOptions as O;
use Modules\Shared\Support\Governorates;
use Modules\Shared\Support\TunisianPhone;

/** PATCH /me: any subset of the editable fields. Unknown keys are dropped. */
final class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'firstName' => ['sometimes', 'required', 'string', 'max:'.O::NAME_MAX],
            'lastName' => ['sometimes', 'nullable', 'string', 'max:'.O::NAME_MAX],
            'gender' => ['sometimes', 'nullable', Rule::in(O::GENDERS)],
            'birthDate' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'email' => ['sometimes', 'nullable', 'string', 'max:254', 'email:rfc'],
            'level' => ['sometimes', 'nullable', Rule::in(O::LEVELS)],
            'skills' => ['sometimes', 'present', 'array', 'max:'.count(O::SKILLS)],
            'skills.*' => ['string', 'distinct', Rule::in(O::SKILLS)],
            'bio' => ['sometimes', 'nullable', 'string', 'max:'.O::BIO_MAX],
            'homeArea' => ['sometimes', 'nullable', 'array:governorate,city'],
            'homeArea.governorate' => ['required_with:homeArea', 'string', Rule::in(Governorates::ALL)],
            'homeArea.city' => ['nullable', 'string', 'max:'.O::CITY_MAX],
            'car' => ['sometimes', 'nullable', 'array:seats'],
            'car.seats' => ['required_with:car', 'integer', 'between:1,'.O::SEATS_MAX],
            'emergencyContact' => ['sometimes', 'nullable', 'array:name,phone'],
            'emergencyContact.name' => ['required_with:emergencyContact', 'string', 'max:'.O::NAME_MAX],
            'emergencyContact.phone' => ['required_with:emergencyContact', 'string', 'regex:'.TunisianPhone::PATTERN],
        ];
    }

    /**
     * Validated input as users columns, only for keys that were sent.
     *
     * @return array<string, mixed>
     */
    public function columns(): array
    {
        $data = $this->validated();
        $columns = [];

        foreach ([
            'firstName' => 'first_name', 'lastName' => 'last_name', 'gender' => 'gender',
            'birthDate' => 'birth_date', 'email' => 'email', 'level' => 'level',
            'skills' => 'skills', 'bio' => 'bio', 'emergencyContact' => 'emergency_contact',
        ] as $key => $column) {
            if (array_key_exists($key, $data)) {
                $columns[$column] = $data[$key];
            }
        }
        if (array_key_exists('homeArea', $data)) {
            $columns['home_governorate'] = $data['homeArea']['governorate'] ?? null;
            $columns['home_city'] = $data['homeArea']['city'] ?? null;
        }
        if (array_key_exists('car', $data)) {
            $columns['car_seats'] = $data['car']['seats'] ?? null;
        }

        return $columns;
    }
}
