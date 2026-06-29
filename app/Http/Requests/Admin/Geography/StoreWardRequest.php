<?php

namespace App\Http\Requests\Admin\Geography;

use App\Models\Ward;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ward::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'union_id' => ['required', 'integer', 'exists:unions,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('wards', 'name')->where('union_id', $this->input('union_id'))],
            'code' => ['nullable', 'string', 'max:64'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geojson' => ['nullable', 'array'],
        ];
    }
}
