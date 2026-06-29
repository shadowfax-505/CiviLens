<?php

namespace App\Http\Requests\Admin\Geography;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('district')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $district = $this->route('district');

        return [
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('districts', 'name')->where('division_id', $this->input('division_id'))->ignore($district)],
            'code' => ['nullable', 'string', 'max:64'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geojson' => ['nullable', 'array'],
        ];
    }
}
