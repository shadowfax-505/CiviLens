<?php

namespace App\Http\Requests\Admin\Procurement;

use App\Models\Award;
use Illuminate\Foundation\Http\FormRequest;

class ApproveAwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $award = $this->route('award');

        return $award instanceof Award
            ? ($this->user()?->can('update', $award->tender) ?? false)
            : false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
        ];
    }
}
