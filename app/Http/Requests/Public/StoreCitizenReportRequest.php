<?php

namespace App\Http\Requests\Public;

use App\Models\CitizenReport;
use Illuminate\Foundation\Http\FormRequest;

class StoreCitizenReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CitizenReport::class) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'citizen_report_category_id' => ['required', 'exists:citizen_report_categories,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'agency_id' => ['nullable', 'exists:agencies,id'],
            'document_id' => ['nullable', 'exists:documents,id'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'upazila_id' => ['nullable', 'exists:upazilas,id'],
            'union_id' => ['nullable', 'exists:unions,id'],
            'ward_id' => ['nullable', 'exists:wards,id'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'location_text' => ['nullable', 'string', 'max:500'],
            'contact_preference' => ['required', 'in:email,phone,none'],
        ];
    }
}
