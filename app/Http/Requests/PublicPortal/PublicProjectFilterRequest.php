<?php

namespace App\Http\Requests\PublicPortal;

use Illuminate\Foundation\Http\FormRequest;

class PublicProjectFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'status_id' => ['nullable', 'integer', 'exists:project_statuses,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->safe()->only(['q', 'agency_id', 'status_id', 'district_id']);
    }
}
