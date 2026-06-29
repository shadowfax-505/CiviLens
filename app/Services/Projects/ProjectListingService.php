<?php

namespace App\Services\Projects;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProjectListingService
{
    public function paginate(Request $request, bool $archived = false): LengthAwarePaginator
    {
        $sort = in_array($request->query('sort'), ['project_code', 'name', 'progress_percentage', 'planned_start_date', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return Project::query()
            ->with(['agency', 'category', 'status', 'priority', 'fundingSource', 'fiscalYear', 'country'])
            ->when($archived, fn (Builder $query) => $query->whereNotNull('archived_at'), fn (Builder $query) => $query->whereNull('archived_at'))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('project_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('short_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('project_code'), fn (Builder $query) => $query->where('project_code', 'like', '%'.$request->query('project_code').'%'))
            ->when($request->filled('name'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->query('name').'%'))
            ->when($request->filled('agency_id'), fn (Builder $query) => $query->where('agency_id', $request->query('agency_id')))
            ->when($request->filled('project_category_id'), fn (Builder $query) => $query->where('project_category_id', $request->query('project_category_id')))
            ->when($request->filled('project_status_id'), fn (Builder $query) => $query->where('project_status_id', $request->query('project_status_id')))
            ->when($request->filled('project_priority_id'), fn (Builder $query) => $query->where('project_priority_id', $request->query('project_priority_id')))
            ->when($request->filled('funding_source_id'), fn (Builder $query) => $query->where('funding_source_id', $request->query('funding_source_id')))
            ->when($request->filled('fiscal_year_id'), fn (Builder $query) => $query->where('fiscal_year_id', $request->query('fiscal_year_id')))
            ->when($request->filled('country_id'), fn (Builder $query) => $query->where('country_id', $request->query('country_id')))
            ->when($request->filled('division_id'), fn (Builder $query) => $query->where('division_id', $request->query('division_id')))
            ->when($request->filled('district_id'), fn (Builder $query) => $query->where('district_id', $request->query('district_id')))
            ->when($request->filled('upazila_id'), fn (Builder $query) => $query->where('upazila_id', $request->query('upazila_id')))
            ->when($request->filled('union_id'), fn (Builder $query) => $query->where('union_id', $request->query('union_id')))
            ->when($request->filled('ward_id'), fn (Builder $query) => $query->where('ward_id', $request->query('ward_id')))
            ->when($request->filled('planned_start_from'), fn (Builder $query) => $query->whereDate('planned_start_date', '>=', $request->query('planned_start_from')))
            ->when($request->filled('planned_start_to'), fn (Builder $query) => $query->whereDate('planned_start_date', '<=', $request->query('planned_start_to')))
            ->when($request->filled('planned_end_from'), fn (Builder $query) => $query->whereDate('planned_end_date', '>=', $request->query('planned_end_from')))
            ->when($request->filled('planned_end_to'), fn (Builder $query) => $query->whereDate('planned_end_date', '<=', $request->query('planned_end_to')))
            ->when($request->filled('progress_min'), fn (Builder $query) => $query->where('progress_percentage', '>=', $request->query('progress_min')))
            ->when($request->filled('progress_max'), fn (Builder $query) => $query->where('progress_percentage', '<=', $request->query('progress_max')))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();
    }
}
