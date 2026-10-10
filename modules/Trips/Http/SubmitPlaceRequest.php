<?php

namespace Modules\Trips\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Shared\Places\PlaceFacts;
use Modules\Shared\Support\Governorates;

final class SubmitPlaceRequest extends FormRequest
{
    public function rules(): array
    {
        $common = [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'latitude' => ['required', 'numeric', 'between:30,38'],
            'longitude' => ['required', 'numeric', 'between:7,12'],
            'governorate' => ['required', Rule::in(Governorates::ALL)],
            'coverage' => ['required', Rule::in(PlaceFacts::COVERAGE)],
            'permit' => ['required', Rule::in(PlaceFacts::PERMIT)],
        ];

        return $this->route('kind') === 'spot'
            ? $common + [
                'type' => ['required', Rule::in(['beach', 'forest', 'desert', 'mountain'])],
                'access' => ['required', Rule::in(['car', 'fourByFour'])],
                'water' => ['required', 'boolean'],
            ]
            : $common + [
                'distanceKm' => ['required', 'numeric', 'between:0.5,200'],
                'elevationGainM' => ['required', 'integer', 'between:0,5000'],
                'durationMin' => ['required', 'integer', 'between:15,4320'],
                'difficulty' => ['required', Rule::in(['easy', 'moderate', 'hard', 'expert'])],
                'shape' => ['required', Rule::in(['loop', 'outAndBack', 'oneWay'])],
                'waterOnRoute' => ['required', 'boolean'],
            ];
    }
}
