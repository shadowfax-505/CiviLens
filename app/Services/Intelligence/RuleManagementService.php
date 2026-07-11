<?php

namespace App\Services\Intelligence;

use App\Models\AnalyticsAlert;
use App\Models\Award;
use App\Models\Budget;
use App\Models\CitizenReport;
use App\Models\ComplianceRecord;
use App\Models\Document;
use App\Models\IntelligenceRule;
use App\Models\IntelligenceRuleAudit;
use App\Models\Project;
use App\Models\SearchJob;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class RuleManagementService
{
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
        $thresholds = is_array($rule->thresholds) ? $rule->thresholds : [];

        return match ($rule->slug) {
            'project-delay-risk' => Project::query()
                ->whereDate('planned_end_date', '<', now()->toDateString())
                ->where('progress_percentage', '<', (int) data_get($thresholds, 'max_progress', 90))
                ->count(),
            'budget-overrun-risk' => Budget::query()
                ->whereColumn('actual_expenditure', '>', 'current_allocation')
                ->count(),
            'low-budget-utilization' => Budget::query()
                ->where('current_allocation', '>', 0)
                ->where('actual_expenditure', '<=', (float) data_get($thresholds, 'max_utilization', 10))
                ->count(),
            'procurement-single-bid-risk' => Tender::query()
                ->has('bidSubmissions', '=', 1)
                ->count(),
            'contractor-compliance-expiry' => ComplianceRecord::query()
                ->whereDate('next_review_date', '<=', now()->addDays(30)->toDateString())
                ->count(),
            'document-missing-metadata' => Document::query()
                ->where(fn ($query) => $query->whereNull('description')->orWhereNull('language'))
                ->count(),
            'document-pending-ocr-readiness' => Document::query()
                ->where('ocr_status', 'pending')
                ->count(),
            'search-indexing-failure' => SearchJob::query()
                ->where('status', 'failed')
                ->count(),
            'analytics-alert-escalation' => AnalyticsAlert::query()
                ->whereIn('severity', ['warning', 'critical'])
                ->where('status', 'open')
                ->count(),
            'procurement-repeat-winner-concentration' => $this->countGrouped(
                Award::query()
                    ->join('bid_submissions', 'awards.bid_submission_id', '=', 'bid_submissions.id')
                    ->where('awards.status', 'approved')
                    ->selectRaw('bid_submissions.bidder_organization_id, count(*) as award_count')
                    ->groupBy('bid_submissions.bidder_organization_id')
                    ->havingRaw('count(*) >= ?', [(int) data_get($thresholds, 'warning', 3)])
                    ->toBase()
            ),
            'citizen-report-cluster' => $this->countGrouped(
                CitizenReport::query()
                    ->whereNull('resolved_at')
                    ->whereNotNull('project_id')
                    ->selectRaw('project_id, count(*) as report_count')
                    ->groupBy('project_id')
                    ->havingRaw('count(*) >= ?', [(int) data_get($thresholds, 'warning', 3)])
                    ->toBase()
            ),
            default => 0,
        };
    }

    private function countGrouped(QueryBuilder $query): int
    {
        return DB::query()->fromSub($query, 'grouped_rule_matches')->count();
    }
}
