<?php

namespace Modules\Trips\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Shared\Places\PlaceKind;
use Modules\Trips\Support\TripKind;

/** POST /trips (every field) and PATCH /trips/{id} (any subset). Cross-field rules run in SaveTrip. */
final class SaveTripRequest extends FormRequest
{
    public function rules(): array
    {
        $create = $this->isMethod('post');
        $req = $create ? 'required' : 'sometimes';

        return [
            'kind' => [$req, Rule::in(TripKind::ALL)],
            'title' => [$req, 'string', 'max:80'],
            'description' => [$req, 'string', 'max:2000'],
            'destination' => [$req, 'array:kind,id'],
            'destination.kind' => ['required_with:destination', Rule::in(PlaceKind::ALL)],
            'destination.id' => ['required_with:destination', 'string', 'max:64'],
            'meetupUrl' => [$req, 'string', 'max:500', 'url:https'],
            'meetupAt' => [$req, 'date'],
            'startsAt' => [$req, 'date'],
            'endsAt' => [$req, 'date'],
            'seats' => [$req, 'integer', 'between:1,20'],
            'priceTnd' => [$create ? 'present' : 'sometimes', 'nullable', 'integer', 'between:1,5000'],
            'womenOnly' => [$create ? 'required' : 'sometimes', 'boolean'],
        ];
    }
}
