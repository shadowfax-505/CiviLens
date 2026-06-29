<?php

namespace App\Http\Requests\Admin\Geography;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUpazilaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('upazila')) ?? false;
    }

    public function rules(): array
    {
        $upazila = $this->route('upazila');

        return [
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('upazilas', 'name')->where('district_id', $this->input('district_id'))->ignore($upazila)],
            'code' => ['nullable', 'string', 'max:64'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geojson' => ['nullable', 'array'],
        ];
    }
}
