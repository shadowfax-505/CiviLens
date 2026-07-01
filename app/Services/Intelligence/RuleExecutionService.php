<?php

namespace App\Services\Intelligence;

use App\Events\IntelligenceIndicatorDetected;
use App\Events\IntelligenceRuleExecuted;
use App\Models\AnalyticsAlert;
use App\Models\Budget;
use App\Models\ComplianceRecord;
use App\Models\Document;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Models\Project;
use App\Models\SearchJob;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RuleExecutionService
{
    public function __construct(
        private readonly EvidenceBuilder $evidence,
        private readonly IndicatorScoringService $scoring,
    ) {}

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    public function run(IntelligenceRule $rule, ?User $user = null): Collection
    {
        $indicators = match ($rule->slug) {
            'project-delay-risk' => $this->projectDelayRisk($rule, $user),
            'budget-overrun-risk' => $this->budgetOverrunRisk($rule, $user),
            'low-budget-utilization' => $this->lowBudgetUtilization($rule, $user),
            'procurement-single-bid-risk' => $this->singleBidRisk($rule, $user),
            'contractor-compliance-expiry' => $this->complianceExpiry($rule, $user),
            'document-missing-metadata' => $this->documentMissingMetadata($rule, $user),
            'document-pending-ocr-readiness' => $this->documentPendingOcr($rule, $user),
            'search-indexing-failure' => $this->searchIndexingFailure($rule, $user),
            'analytics-alert-escalation' => $this->analyticsAlertEscalation($rule, $user),
            default => collect(),
        };

        IntelligenceRuleExecuted::dispatch($rule, $indicators->count());

        return $indicators;
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    private function projectDelayRisk(IntelligenceRule $rule, ?User $user): Collection
    {
        $thresholds = $rule->thresholds ?? [];
        $maxProgress = (int) ($thresholds['max_progress'] ?? 90);

        return Project::query()
            ->whereDate('planned_end_date', '<', now()->toDateString())
            ->where('progress_percentage', '<', $maxProgress)
            ->limit(25)
            ->get()
            ->map(fn (Project $project): IntelligenceIndicator => $this->createIndicator(
                $rule,
                $project,
                'Project delay risk detected',
                $project->name.' has passed the planned end date with '.$project->progress_percentage.'% progress.',
                max(1, now()->diffInDays($project->planned_end_date)),
                ['days_overdue' => max(1, now()->diffInDays($project->planned_end_date)), 'progress' => $project->progress_percentage],
                $user,
            ));
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    private function budgetOverrunRisk(IntelligenceRule $rule, ?User $user): Collection
    {
        return Budget::query()
            ->whereColumn('actual_expenditure', '>', 'current_allocation')
            ->limit(25)
            ->get()
            ->map(fn (Budget $budget): IntelligenceIndicator => $this->createIndicator(
                $rule,
                $budget,
                'Budget overrun risk detected',
                'Actual expenditure is above current allocation.',
                (float) $budget->utilization_percentage,
                ['utilization_percentage' => $budget->utilization_percentage],
                $user,
            ));
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    private function lowBudgetUtilization(IntelligenceRule $rule, ?User $user): Collection
    {
        $maxUtilization = (float) (($rule->thresholds ?? [])['max_utilization'] ?? 10);

        return Budget::query()
            ->where('current_allocation', '>', 0)
            ->where('actual_expenditure', '<=', $maxUtilization)
            ->limit(25)
            ->get()
            ->map(fn (Budget $budget): IntelligenceIndicator => $this->createIndicator(
                $rule,
                $budget,
                'Low budget utilization detected',
                'Budget spending is low relative to allocation.',
                50,
                ['utilization_percentage' => $budget->utilization_percentage],
                $user,
            ));
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    private function singleBidRisk(IntelligenceRule $rule, ?User $user): Collection
    {
        return Tender::query()
            ->withCount('bidSubmissions')
            ->having('bid_submissions_count', '<=', 1)
            ->limit(25)
            ->get()
            ->map(fn (Tender $tender): IntelligenceIndicator => $this->createIndicator(
                $rule,
                $tender,
                'Single bid procurement signal',
                $tender->title.' has low bidder participation.',
                75,
                ['bid_count' => $tender->bid_submissions_count],
                $user,
            ));
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    private function complianceExpiry(IntelligenceRule $rule, ?User $user): Collection
    {
        return ComplianceRecord::query()
            ->whereDate('next_review_date', '<=', now()->addDays(30)->toDateString())
            ->limit(25)
            ->get()
            ->map(fn (ComplianceRecord $record): IntelligenceIndicator => $this->createIndicator(
                $rule,
                $record,
                'Contractor compliance review due',
                'A compliance record is due for review.',
                70,
                ['next_review_date' => $record->next_review_date ? Carbon::parse($record->next_review_date)->toDateString() : null],
                $user,
            ));
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    private function documentMissingMetadata(IntelligenceRule $rule, ?User $user): Collection
    {
        return Document::query()
            ->where(function ($query): void {
                $query->whereNull('description')->orWhereNull('language');
            })
            ->limit(25)
            ->get()
            ->map(fn (Document $document): IntelligenceIndicator => $this->createIndicator(
                $rule,
                $document,
                'Document metadata gap',
                $document->title.' is missing descriptive metadata.',
                60,
                ['document_id' => $document->id],
                $user,
            ));
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    private function documentPendingOcr(IntelligenceRule $rule, ?User $user): Collection
    {
        return Document::query()
            ->where('ocr_status', 'pending')
            ->limit(25)
            ->get()
            ->map(fn (Document $document): IntelligenceIndicator => $this->createIndicator(
                $rule,
                $document,
                'Document pending OCR readiness',
                $document->title.' is queued-ready for future OCR processing.',
                40,
                ['ocr_status' => $document->ocr_status],
                $user,
            ));
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    private function searchIndexingFailure(IntelligenceRule $rule, ?User $user): Collection
    {
        return SearchJob::query()
            ->where('status', 'failed')
            ->limit(25)
            ->get()
            ->map(fn (SearchJob $job): IntelligenceIndicator => $this->createIndicator(
                $rule,
                $job,
                'Search indexing failure',
                'A search indexing job failed and needs review.',
                80,
                ['search_job_id' => $job->id],
                $user,
            ));
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    private function analyticsAlertEscalation(IntelligenceRule $rule, ?User $user): Collection
    {
        return AnalyticsAlert::query()
            ->whereIn('severity', ['warning', 'critical'])
            ->where('status', 'open')
            ->limit(25)
            ->get()
            ->map(fn (AnalyticsAlert $alert): IntelligenceIndicator => $this->createIndicator(
                $rule,
                $alert,
                'Analytics alert requires intelligence review',
                $alert->title,
                $alert->severity === 'critical' ? 95 : 65,
                ['analytics_alert_id' => $alert->id],
                $user,
            ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createIndicator(IntelligenceRule $rule, Model $source, string $title, string $description, float $value, array $payload, ?User $user): IntelligenceIndicator
    {
        $thresholds = is_array($rule->thresholds) ? $rule->thresholds : [];
        $score = $this->scoring->score($value, $thresholds);

        $indicator = IntelligenceIndicator::query()->create([
            'intelligence_rule_id' => $rule->id,
            'source_type' => $source::class,
            'source_id' => $source->getKey(),
            'module' => $rule->module,
            'title' => $title,
            'description' => $description,
            'severity' => $score['severity'],
            'confidence_score' => $score['confidence'],
            'status' => 'pending',
            'detected_at' => now(),
            'rule_version' => $rule->version,
            'detection_payload' => $payload,
            'metadata' => ['rule_slug' => $rule->slug],
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        $this->evidence->link($indicator, $source, [
            'label' => 'Source record',
            'summary' => $description,
            'weight' => $score['confidence'],
            'payload' => $payload,
        ], $user);

        IntelligenceIndicatorDetected::dispatch($indicator);

        return $indicator;
    }
}
