<?php

namespace App\Http\Requests\Admin\Contractors;

use Illuminate\Foundation\Http\FormRequest;

class ContractorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization !== null && ($this->user()?->can('update', $organization) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contractor_category_id' => ['required', 'integer', 'exists:contractor_categories,id'],
            'contractor_classification_id' => ['required', 'integer', 'exists:contractor_classifications,id'],
            'contractor_registration_status_id' => ['required', 'integer', 'exists:contractor_registration_statuses,id'],
            'contractor_risk_level_id' => ['required', 'integer', 'exists:contractor_risk_levels,id'],
            'is_active' => ['nullable', 'boolean'],
            'is_suspended' => ['nullable', 'boolean'],
            'is_blacklisted' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
        ];
    }
}
