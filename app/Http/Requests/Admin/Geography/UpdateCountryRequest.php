<?php

namespace App\Http\Requests\Admin\Geography;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('country')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $country = $this->route('country');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('countries', 'name')->ignore($country)],
            'iso2' => ['required', 'string', 'size:2', Rule::unique('countries', 'iso2')->ignore($country)],
            'iso3' => ['required', 'string', 'size:3', Rule::unique('countries', 'iso3')->ignore($country)],
            'phone_code' => ['nullable', 'string', 'max:16'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geojson' => ['nullable', 'array'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ];
    }
}
