<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDistrictPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'district_ids' => ['sometimes', 'array', 'max:8'],
            'district_ids.*' => ['integer', 'distinct', 'exists:districts,id'],
        ];
    }
}
