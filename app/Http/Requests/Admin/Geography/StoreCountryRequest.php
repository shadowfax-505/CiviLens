<?php

namespace App\Http\Requests\Admin\Geography;

use App\Models\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Country::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('countries', 'name')],
            'iso2' => ['required', 'string', 'size:2', Rule::unique('countries', 'iso2')],
            'iso3' => ['required', 'string', 'size:3', Rule::unique('countries', 'iso3')],
            'phone_code' => ['nullable', 'string', 'max:16'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geojson' => ['nullable', 'array'],
        ];
    }
}
