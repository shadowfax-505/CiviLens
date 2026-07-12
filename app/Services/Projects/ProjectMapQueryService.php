<?php

namespace App\Services\Projects;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ProjectMapQueryService
{
    /**
     * @param  array<string, int|float>  $viewport
     * @return Collection<int, array<string, int|float|string|null>>
     */
    public function publicMarkers(array $viewport = []): Collection
    {
        return $this->markers($viewport, true)->map(fn (Model $project): array => $this->publicMarker($project));
    }

    /**
     * @param  array<string, int|float>  $viewport
     * @return Collection<int, array<string, int|float|string|null>>
     */
    public function adminMarkers(array $viewport = []): Collection
    {
        return $this->markers($viewport, false)->map(fn (Model $project): array => $this->adminMarker($project));
    }

    /**
     * Latitude and longitude remain the portable source for this query. The optional
     * MySQL POINT column is an additive optimization and is intentionally not required.
     *
     * @param  array<string, int|float>  $viewport
     * @return Collection<int, Project>
     */
    private function markers(array $viewport, bool $publicOnly): Collection
    {
        $limit = $publicOnly
            ? (int) config('civiclens.maps.public_marker_limit')
            : (int) config('civiclens.maps.admin_marker_limit');

        $query = $this->coordinateQuery($viewport)->with(['agency:id,name', 'status:id,name']);

        if ($publicOnly) {
            $query->where('is_public', true)
                ->where('is_active', true)
                ->whereNull('archived_at');
        }

        return $query
            ->orderBy('name')
            ->limit($limit + 1)
            ->get();
    }

    /**
     * @param  array<string, int|float>  $viewport
     * @return Builder<Project>
     */
    private function coordinateQuery(array $viewport): Builder
    {
        return Project::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($viewport !== [], function (Builder $query) use ($viewport): void {
                $query->whereBetween('latitude', [$viewport['south'], $viewport['north']])
                    ->whereBetween('longitude', [$viewport['west'], $viewport['east']]);
            });
    }

    /** @return array<string, int|float|string|null> */
    private function publicMarker(Model $project): array
    {
        return array_merge($this->marker($project), ['url' => route('public.projects.show', $project)]);
    }

    /** @return array<string, int|float|string|null> */
    private function adminMarker(Model $project): array
    {
        return array_merge(['id' => (int) $project->getKey()], $this->marker($project), ['url' => route('admin.projects.show', $project)]);
    }

    /** @return array<string, float|string|null> */
    private function marker(Model $project): array
    {
        $agency = $project->getRelation('agency');
        $status = $project->getRelation('status');

        return [
            'name' => (string) $project->getAttribute('name'),
            'latitude' => (float) $project->getAttribute('latitude'),
            'longitude' => (float) $project->getAttribute('longitude'),
            'agency' => $agency instanceof Model ? (string) $agency->getAttribute('name') : null,
            'status' => $status instanceof Model ? (string) $status->getAttribute('name') : null,
        ];
    }
}
