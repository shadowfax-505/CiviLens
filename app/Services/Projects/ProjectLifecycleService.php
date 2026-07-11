<?php

namespace App\Services\Projects;

use App\Events\ProjectCreated;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ProjectLifecycleService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?Request $request = null): Project
    {
        $project = Project::query()->create(array_merge($data, [
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'is_public' => (bool) ($data['is_public'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]));

        $this->log($project, 'created', $actor, $request, null, $project->only(['project_code', 'name']));
        ProjectCreated::dispatch($project, $actor);

        return $project;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data, User $actor, ?Request $request = null): Project
    {
        $oldStatus = $project->project_status_id;
        $oldProgress = $project->progress_percentage;
        $trackedFields = [
            'name',
            'project_status_id',
            'progress_percentage',
            'country_id',
            'division_id',
            'district_id',
            'upazila_id',
            'union_id',
            'ward_id',
            'latitude',
            'longitude',
            'geojson',
        ];
        $original = $project->only($trackedFields);

        $payload = array_merge($data, [
            'updated_by' => $actor->id,
            'is_public' => array_key_exists('is_public', $data) ? (bool) $data['is_public'] : $project->is_public,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $project->is_active,
        ]);

        $project->update($payload);

        $this->log($project, 'updated', $actor, $request, $original, $project->only($trackedFields));

        $originalLocation = Arr::only($original, ['country_id', 'division_id', 'district_id', 'upazila_id', 'union_id', 'ward_id', 'latitude', 'longitude', 'geojson']);
        $newLocation = $project->only(['country_id', 'division_id', 'district_id', 'upazila_id', 'union_id', 'ward_id', 'latitude', 'longitude', 'geojson']);

        if ($newLocation !== $originalLocation) {
            $this->log(
                $project,
                'location_updated',
                $actor,
                $request,
                $originalLocation,
                $newLocation,
            );
        }

        if ((int) $oldStatus !== (int) $project->project_status_id) {
            $this->log($project, 'status_changed', $actor, $request, ['project_status_id' => $oldStatus], ['project_status_id' => $project->project_status_id]);
        }

        if ((int) $oldProgress !== (int) $project->progress_percentage) {
            $this->log($project, 'progress_updated', $actor, $request, ['progress_percentage' => $oldProgress], ['progress_percentage' => $project->progress_percentage]);
        }

        return $project;
    }

    public function archive(Project $project, User $actor, ?Request $request = null): void
    {
        $project->update([
            'archived_at' => now(),
            'is_active' => false,
            'updated_by' => $actor->id,
        ]);

        $this->log($project, 'archived', $actor, $request);
    }

    public function restore(Project $project, User $actor, ?Request $request = null): void
    {
        $project->update([
            'archived_at' => null,
            'is_active' => true,
            'updated_by' => $actor->id,
        ]);

        $this->log($project, 'restored', $actor, $request);
    }

    public function delete(Project $project, User $actor, ?Request $request = null): void
    {
        $this->log($project, 'deleted', $actor, $request);
        $project->delete();
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function log(Project $project, string $event, User $actor, ?Request $request = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        $project->activities()->create([
            'actor_id' => $actor->id,
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
