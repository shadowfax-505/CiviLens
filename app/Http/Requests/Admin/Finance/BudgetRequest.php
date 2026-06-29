<?php

namespace App\Http\Requests\Admin\Finance;

use App\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;

class BudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget
            ? ($this->user()?->can('update', $budget) ?? false)
            : ($this->user()?->can('create', Budget::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'funding_source_id' => ['required', 'integer', 'exists:funding_sources,id'],
            'budget_category_id' => ['required', 'integer', 'exists:budget_categories,id'],
            'budget_type_id' => ['required', 'integer', 'exists:budget_types,id'],
            'budget_status_id' => ['required', 'integer', 'exists:budget_statuses,id'],
            'original_allocation' => ['required', 'numeric', 'min:0'],
            'current_allocation' => ['required', 'numeric', 'min:0'],
            'reserved_amount' => ['nullable', 'numeric', 'min:0'],
            'committed_amount' => ['nullable', 'numeric', 'min:0'],
            'actual_expenditure' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
