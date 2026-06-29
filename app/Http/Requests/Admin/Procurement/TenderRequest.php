<?php

namespace App\Http\Requests\Admin\Procurement;

use App\Models\Tender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tender = $this->route('tender');

        return $tender
            ? ($this->user()?->can('update', $tender) ?? false)
            : ($this->user()?->can('create', Tender::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tender = $this->route('tender');
        $tenderId = $tender instanceof Tender ? $tender->id : null;

        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'budget_id' => ['required', 'integer', 'exists:budgets,id'],
            'agency_id' => ['required', 'integer', 'exists:agencies,id'],
            'procurement_method_id' => ['required', 'integer', 'exists:procurement_methods,id'],
            'tender_category_id' => ['required', 'integer', 'exists:tender_categories,id'],
            'tender_status_id' => ['required', 'integer', 'exists:tender_statuses,id'],
            'tender_number' => ['required', 'string', 'max:255', Rule::unique('tenders', 'tender_number')->ignore($tenderId)],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tenders', 'slug')->ignore($tenderId)],
            'description' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
            'closing_at' => ['nullable', 'date', 'after_or_equal:published_at'],
            'is_public' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
