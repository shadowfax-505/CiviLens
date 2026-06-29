<?php

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;

class BudgetRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('budget')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'new_allocation' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string'],
            'approval_date' => ['nullable', 'date'],
            'approved_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
