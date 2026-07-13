<?php

namespace App\Http\Requests\Admin\Contractors;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization
            ? ($this->user()?->can('update', $organization) ?? false)
            : ($this->user()?->can('create', Organization::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'organization_company_type_id' => ['required', 'integer', 'exists:organization_company_types,id'],
            'organization_industry_id' => ['required', 'integer', 'exists:organization_industries,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'upazila_id' => ['nullable', 'integer', 'exists:upazilas,id'],
            'union_id' => ['nullable', 'integer', 'exists:unions,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:100', Rule::unique('organizations', 'registration_number')->ignore($organization)],
            'tax_identification_number' => ['nullable', 'string', 'max:100', Rule::unique('organizations', 'tax_identification_number')->ignore($organization)],
            'website' => ['nullable', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'headquarters_address' => ['nullable', 'string'],
            'headquarters_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'headquarters_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'headquarters_geojson' => ['nullable', 'array'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'suspended', 'archived'])],
            'established_date' => ['nullable', 'date'],
        ];
    }
}
