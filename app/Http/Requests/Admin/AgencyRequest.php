<?php

namespace App\Http\Requests\Admin;

use App\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agency = $this->route('agency');

        return $agency
            ? ($this->user()?->can('update', $agency) ?? false)
            : ($this->user()?->can('create', Agency::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $agency = $this->route('agency');
        $agencyId = $agency instanceof Agency ? $agency->id : null;

        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')->whereNull('deleted_at'), Rule::notIn([$agencyId])],
            'agency_type_id' => ['required', 'integer', 'exists:agency_types,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'upazila_id' => ['nullable', 'integer', 'exists:upazilas,id'],
            'union_id' => ['nullable', 'integer', 'exists:unions,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:64'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('agencies', 'slug')->ignore($agency)],
            'description' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Agency::STATUSES)],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
