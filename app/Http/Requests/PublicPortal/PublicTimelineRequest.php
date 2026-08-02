<?php

namespace App\Http\Requests\PublicPortal;

use Illuminate\Foundation\Http\FormRequest;

class PublicTimelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'timeline_type' => ['nullable', 'string', 'in:project,procurement,document'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'publisher_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'source_class' => ['nullable', 'string', 'in:government,non-government'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->safe()->only([
            'timeline_type',
            'district_id',
            'publisher_id',
            'date_from',
            'date_to',
            'source_class',
        ]);
    }
}
