<?php

namespace App\Http\Requests\Admin\Geography;

use App\Models\Upazila;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUpazilaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Upazila::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('upazilas', 'name')->where('district_id', $this->input('district_id'))],
            'code' => ['nullable', 'string', 'max:64'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geojson' => ['nullable', 'array'],
        ];
    }
}
