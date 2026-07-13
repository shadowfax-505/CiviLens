<?php

namespace App\Http\Requests\Admin\Geography;

use App\Http\Requests\Concerns\ValidatesProjectLocation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectLocationRequest extends FormRequest
{
    use ValidatesProjectLocation;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'upazila_id' => ['nullable', 'integer', 'exists:upazilas,id'],
            'union_id' => ['nullable', 'integer', 'exists:unions,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
