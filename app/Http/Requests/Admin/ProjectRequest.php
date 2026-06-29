<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project
            ? ($this->user()?->can('update', $project) ?? false)
            : ($this->user()?->can('create', Project::class) ?? false);
    }

    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'project_code' => ['required', 'string', 'max:64', Rule::unique('projects', 'project_code')->ignore($project)],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:64'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('projects', 'slug')->ignore($project)],
            'description' => ['nullable', 'string'],
            'agency_id' => ['required', 'integer', 'exists:agencies,id'],
            'parent_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at'), Rule::notIn([$project?->id])],
            'project_category_id' => ['required', 'integer', 'exists:project_categories,id'],
            'project_status_id' => ['required', 'integer', 'exists:project_statuses,id'],
            'project_priority_id' => ['required', 'integer', 'exists:project_priorities,id'],
            'funding_source_id' => ['required', 'integer', 'exists:funding_sources,id'],
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'upazila_id' => ['nullable', 'integer', 'exists:upazilas,id'],
            'union_id' => ['nullable', 'integer', 'exists:unions,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'planned_start_date' => ['nullable', 'date'],
            'actual_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geojson' => ['nullable', 'array'],
            'featured_image_path' => ['nullable', 'string', 'max:255'],
            'is_public' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
