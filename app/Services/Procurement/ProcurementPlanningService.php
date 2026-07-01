<?php

namespace App\Services\Procurement;

use App\Events\ProcurementPlanApproved;
use App\Models\ProcurementPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProcurementPlanningService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): ProcurementPlan
    {
        return DB::transaction(function () use ($data, $actor): ProcurementPlan {
            $plan = ProcurementPlan::query()->create($data);
            $this->activity($plan, $actor, 'procurement_plan.created', 'Procurement plan created.', newValues: $plan->only(['plan_number', 'title', 'status']));

            return $plan;
        });
    }

    public function approve(ProcurementPlan $plan, User $actor, ?string $notes = null): ProcurementPlan
    {
        return DB::transaction(function () use ($plan, $actor, $notes): ProcurementPlan {
            $old = $plan->only(['status', 'approved_by', 'approved_at']);
            $plan->forceFill([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approval_notes' => $notes,
            ])->save();

            $this->activity($plan, $actor, 'procurement_plan.approved', $notes ?? 'Procurement plan approved.', $old, $plan->only(['status', 'approved_by', 'approved_at']));
            ProcurementPlanApproved::dispatch($plan, $actor);

            return $plan->refresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function activity(ProcurementPlan $plan, User $actor, string $event, string $description, ?array $oldValues = null, ?array $newValues = null): void
    {
        $plan->activities()->create([
            'actor_id' => $actor->id,
            'event' => $event,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
