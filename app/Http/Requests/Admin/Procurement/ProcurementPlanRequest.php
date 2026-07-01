<?php

namespace App\Http\Requests\Admin\Procurement;

use App\Models\ProcurementPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcurementPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProcurementPlan::class) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan_number' => ['required', 'string', 'max:255', Rule::unique('procurement_plans', 'plan_number')],
            'title' => ['required', 'string', 'max:255'],
            'agency_id' => ['required', 'exists:agencies,id'],
            'budget_id' => ['required', 'exists:budgets,id'],
            'project_id' => ['required', 'exists:projects,id'],
            'fiscal_year_id' => ['required', 'exists:fiscal_years,id'],
            'funding_source_id' => ['required', 'exists:funding_sources,id'],
            'procurement_method_id' => ['required', 'exists:procurement_methods,id'],
            'estimated_value' => ['required', 'numeric', 'min:0'],
            'priority' => ['required', 'string', 'max:50'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_award_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'planned_completion_date' => ['nullable', 'date', 'after_or_equal:planned_award_date'],
            'status' => ['required', 'string', 'max:50'],
        ];
    }
}
