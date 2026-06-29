<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

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

        return $project;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data, User $actor, ?Request $request = null): Project
    {
        $oldStatus = $project->project_status_id;
        $oldProgress = $project->progress_percentage;
        $original = $project->only(['name', 'project_status_id', 'progress_percentage', 'approved_budget', 'spent_amount']);

        $project->update(array_merge($data, [
            'updated_by' => $actor->id,
            'is_public' => (bool) ($data['is_public'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]));

        $this->log($project, 'updated', $actor, $request, $original, $project->only(array_keys($original)));

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
