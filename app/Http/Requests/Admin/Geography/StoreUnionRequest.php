<?php

namespace App\Http\Requests\Admin\Geography;

use App\Models\AdministrativeUnion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AdministrativeUnion::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'upazila_id' => ['required', 'integer', 'exists:upazilas,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('unions', 'name')->where('upazila_id', $this->input('upazila_id'))],
            'type' => ['required', 'string', Rule::in(['union', 'municipality'])],
            'code' => ['nullable', 'string', 'max:64'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geojson' => ['nullable', 'array'],
        ];
    }
}
