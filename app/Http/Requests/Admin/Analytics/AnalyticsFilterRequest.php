<?php

namespace App\Http\Requests\Admin\Analytics;

use App\Models\AnalyticsReport;
use App\Support\Analytics\AnalyticsFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AnalyticsReport::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dashboard' => ['nullable', Rule::in(['executive', 'finance', 'procurement', 'contractors', 'agency', 'projects', 'search', 'system'])],
            'metric' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])],
            'format' => ['nullable', Rule::in(['csv', 'pdf', 'xlsx'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'contractor_id' => ['nullable', 'integer', 'exists:contractor_profiles,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'budget_id' => ['nullable', 'integer', 'exists:budgets,id'],
            'funding_source_id' => ['nullable', 'integer', 'exists:funding_sources,id'],
            'procurement_method_id' => ['nullable', 'integer', 'exists:procurement_methods,id'],
        ];
    }

    public function filters(): AnalyticsFilters
    {
        return AnalyticsFilters::fromArray($this->validated());
    }
}
