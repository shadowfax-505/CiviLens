<?php

namespace App\Services\PublicPortal;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublicProjectService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Project>
     */
    public function listing(array $filters = []): LengthAwarePaginator
    {
        return Project::query()
            ->with(['agency', 'status', 'category', 'priority', 'country', 'division', 'district', 'upazila', 'union', 'ward'])
            ->where('is_public', true)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->when($filters['q'] ?? null, function ($builder, string $query): void {
                $builder->where(function ($builder) use ($query): void {
                    $builder->where('name', 'like', '%'.$query.'%')
                        ->orWhere('project_code', 'like', '%'.$query.'%')
                        ->orWhere('description', 'like', '%'.$query.'%');
                });
            })
            ->when($filters['agency_id'] ?? null, fn ($builder, int $agencyId) => $builder->where('agency_id', $agencyId))
            ->when($filters['status_id'] ?? null, fn ($builder, int $statusId) => $builder->where('project_status_id', $statusId))
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Project $project): array
    {
        abort_unless($project->is_public && $project->is_active && $project->archived_at === null, 404);

        $project->load(['agency', 'status', 'category', 'priority', 'country', 'division', 'district', 'upazila', 'union', 'ward', 'budgets.status', 'activities']);

        return [
            'project' => $project,
            'budgets' => $project->budgets()->with(['status', 'fiscalYear'])->latest()->limit(5)->get(),
            'tenders' => $project->tenders()->with(['status', 'method'])->where('is_public', true)->where('is_active', true)->whereNull('archived_at')->latest()->limit(6)->get(),
            'documents' => app(PublicDocumentService::class)->forProject($project),
            'activities' => $project->activities()->limit(8)->get(),
        ];
    }
}
