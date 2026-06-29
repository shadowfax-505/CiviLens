<?php

namespace App\Services\Contractors;

use App\Events\ContractorRegistered;
use App\Events\PerformanceSnapshotCreated;
use App\Jobs\RecalculateContractorRiskScore;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\ContractorActivity;
use App\Models\ContractorActivityType;
use App\Models\ContractorPerformanceSnapshot;
use App\Models\ContractorProfile;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContractorLifecycleService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createOrganization(array $attributes, User $actor): Organization
    {
        /** @var Organization $organization */
        $organization = Organization::query()->create(array_merge($attributes, [
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]));

        return $organization;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrganization(Organization $organization, array $attributes, User $actor): Organization
    {
        $organization->forceFill(array_merge($attributes, [
            'updated_by' => $actor->id,
        ]))->save();

        return $organization;
    }

    public function archiveOrganization(Organization $organization): void
    {
        $organization->forceFill([
            'archived_at' => now(),
            'status' => 'archived',
        ])->save();
    }

    public function restoreOrganization(Organization $organization): void
    {
        $organization->forceFill([
            'archived_at' => null,
            'status' => 'active',
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function registerProfile(Organization $organization, array $attributes): ContractorProfile
    {
        return DB::transaction(function () use ($organization, $attributes): ContractorProfile {
            /** @var ContractorProfile $profile */
            $profile = ContractorProfile::query()->updateOrCreate(
                ['organization_id' => $organization->id],
                $attributes,
            );

            $this->recordActivity($profile, 'contractor.registered', 'Contractor profile registered.', $attributes);

            ContractorRegistered::dispatch($profile);
            RecalculateContractorRiskScore::dispatch($profile->id);

            return $profile;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPerformanceSnapshot(ContractorProfile $profile, Contract $contract, array $attributes): ContractorPerformanceSnapshot
    {
        $planned = isset($attributes['planned_completion_date']) ? Carbon::parse($attributes['planned_completion_date']) : null;
        $actual = isset($attributes['actual_completion_date']) ? Carbon::parse($attributes['actual_completion_date']) : null;
        $delayDays = $planned !== null && $actual !== null ? max(0, $planned->diffInDays($actual, false)) : 0;

        $budget = $contract->budget;
        $project = $contract->project;

        if (! $project instanceof Project) {
            throw new \LogicException('Contract performance snapshots require a project relationship.');
        }

        $finalCost = (float) ($attributes['final_cost'] ?? 0);
        $costVariance = $budget instanceof Budget ? round($finalCost - (float) $budget->current_allocation, 2) : 0.0;

        /** @var ContractorPerformanceSnapshot $snapshot */
        $snapshot = ContractorPerformanceSnapshot::query()->create(array_merge($attributes, [
            'contractor_profile_id' => $profile->id,
            'project_id' => $contract->project_id,
            'contract_id' => $contract->id,
            'agency_id' => $project->agency_id,
            'budget_id' => $contract->budget_id,
            'delay_days' => $delayDays,
            'cost_variance' => $costVariance,
        ]));

        $this->recordActivity($profile, 'contractor.performance_snapshot_created', 'Performance snapshot created.', [
            'contract_id' => $contract->id,
            'snapshot_id' => $snapshot->id,
        ]);

        PerformanceSnapshotCreated::dispatch($snapshot);
        RecalculateContractorRiskScore::dispatch($profile->id);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function recordActivity(ContractorProfile $profile, string $event, string $description, array $values = []): ContractorActivity
    {
        /** @var ContractorActivity $activity */
        $activity = ContractorActivity::query()->create([
            'contractor_profile_id' => $profile->id,
            'organization_id' => $profile->organization_id,
            'contractor_activity_type_id' => ContractorActivityType::query()->where('slug', 'registered')->value('id'),
            'event' => $event,
            'description' => $description,
            'new_values' => $values,
        ]);

        return $activity;
    }
}
