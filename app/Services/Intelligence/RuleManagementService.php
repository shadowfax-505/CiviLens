<?php

namespace App\Services\Intelligence;

use App\Models\IntelligenceRule;
use App\Models\IntelligenceRuleAudit;
use App\Models\User;

class RuleManagementService
{
    public function __construct(private readonly IntelligenceCandidateQueryService $candidates) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(IntelligenceRule $rule, User $actor, array $data): IntelligenceRule
    {
        $before = $this->snapshot($rule);

        $rule->forceFill($data + ['updated_by' => $actor->id])->save();

        IntelligenceRuleAudit::query()->create([
            'intelligence_rule_id' => $rule->id,
            'actor_id' => $actor->id,
            'event' => 'rule.updated',
            'before' => $before,
            'after' => $this->snapshot($rule->refresh()),
            'occurred_at' => now(),
        ]);

        return $rule;
    }

    /**
     * @return array<string, mixed>
     */
    public function dryRun(IntelligenceRule $rule): array
    {
        return [
            'rule' => $rule->only(['id', 'name', 'slug', 'module', 'category', 'severity_default', 'version']),
            'dry_run' => true,
            'estimated_matches' => $this->estimateMatches($rule),
            'thresholds' => $rule->thresholds ?? [],
            'configuration' => $rule->configuration ?? [],
            'explanation' => 'Dry run estimates source records that match the configured deterministic rule without creating indicators or evidence.',
            'generated_at' => date(DATE_ATOM),
        ];
    }

    public function recordExecution(IntelligenceRule $rule, int $durationMs, ?User $actor = null): void
    {
        $actorId = $actor instanceof User ? $actor->id : null;

        $rule->forceFill([
            'last_executed_at' => now(),
            'last_execution_ms' => $durationMs,
            'updated_by' => $actorId ?? $rule->updated_by,
        ])->save();

        IntelligenceRuleAudit::query()->create([
            'intelligence_rule_id' => $rule->id,
            'actor_id' => $actorId,
            'event' => 'rule.executed',
            'before' => null,
            'after' => ['last_execution_ms' => $durationMs],
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(IntelligenceRule $rule): array
    {
        return $rule->only([
            'name',
            'slug',
            'module',
            'category',
            'severity_default',
            'priority',
            'weight',
            'thresholds',
            'configuration',
            'execution_frequency',
            'documentation_url',
            'description',
            'version',
            'is_active',
        ]);
    }

    private function estimateMatches(IntelligenceRule $rule): int
    {
        return $this->candidates->count($rule);
    }
}
