<?php

namespace App\Services\Intelligence;

use App\Events\IntelligenceIndicatorDetected;
use App\Events\IntelligenceRuleExecuted;
use App\Models\AnalyticsAlert;
use App\Models\Award;
use App\Models\BidderOrganization;
use App\Models\Budget;
use App\Models\CitizenReport;
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
        private readonly RuleManagementService $management,
        private readonly IntelligenceCandidateQueryService $candidates,
    ) {}

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    public function run(IntelligenceRule $rule, ?User $user = null): Collection
    {
        $startedAt = microtime(true);

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
            'procurement-repeat-winner-concentration' => $this->repeatWinnerConcentration($rule, $user),
            'citizen-report-cluster' => $this->citizenReportCluster($rule, $user),
            default => collect(),
        };

        IntelligenceRuleExecuted::dispatch($rule, $indicators->count());
        $this->management->recordExecution($rule, (int) ((microtime(true) - $startedAt) * 1000), $user);

        return $indicators;
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    private function projectDelayRisk(IntelligenceRule $rule, ?User $user): Collection
    {
        return $this->candidates->apply($rule, Project::query())
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
        return $this->candidates->apply($rule, Budget::query())
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
        return $this->candidates->apply($rule, Budget::query())
            ->limit(25)
            ->get()
            ->map(fn (Budget $budget): IntelligenceIndicator => $this->createIndicator(
                $rule,
                $budget,
                'Low budget utilization detected',
                'Budget spending is low relative to allocation.',
                100 - (float) $budget->utilization_percentage,
                ['utilization_percentage' => $budget->utilization_percentage],
                $user,
            ));
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    private function singleBidRisk(IntelligenceRule $rule, ?User $user): Collection
    {
        return $this->candidates->apply($rule, Tender::query())
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
        return $this->candidates->apply($rule, ComplianceRecord::query())
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
        return $this->candidates->apply($rule, Document::query())
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
        return $this->candidates->apply($rule, Document::query())
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
        return $this->candidates->apply($rule, SearchJob::query())
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
        return $this->candidates->apply($rule, AnalyticsAlert::query())
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
     * @return Collection<int, IntelligenceIndicator>
     */
    private function repeatWinnerConcentration(IntelligenceRule $rule, ?User $user): Collection
    {
        $thresholds = is_array($rule->thresholds) ? $rule->thresholds : [];

        return $this->candidates->apply($rule, Award::query())
            ->limit(25)
            ->get()
            ->map(function (Award $row) use ($rule, $user, $thresholds): ?IntelligenceIndicator {
                $bidderId = $row->getAttribute('bidder_id');
                $awardCount = $row->getAttribute('award_count');
                $bidder = BidderOrganization::query()->find((int) $bidderId);
                if (! $bidder instanceof BidderOrganization) {
                    return null;
                }

                return $this->createIndicator(
                    $rule,
                    $bidder,
                    'Repeat award concentration signal',
                    $bidder->name.' has a concentrated pattern of approved awards requiring human review.',
                    (float) $awardCount,
                    ['award_count' => (int) $awardCount, 'thresholds' => $thresholds],
                    $user,
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    private function citizenReportCluster(IntelligenceRule $rule, ?User $user): Collection
    {
        $thresholds = is_array($rule->thresholds) ? $rule->thresholds : [];

        return $this->candidates->apply($rule, CitizenReport::query())
            ->limit(25)
            ->get()
            ->map(function (CitizenReport $cluster) use ($rule, $user, $thresholds): ?IntelligenceIndicator {
                $project = Project::query()->find($cluster->project_id);
                if (! $project instanceof Project) {
                    return null;
                }

                return $this->createIndicator(
                    $rule,
                    $project,
                    'Citizen report cluster signal',
                    $project->name.' has multiple unresolved citizen reports requiring triage.',
                    (float) $cluster->getAttribute('report_count'),
                    ['report_count' => (int) $cluster->getAttribute('report_count'), 'thresholds' => $thresholds],
                    $user,
                );
            })
            ->filter()
            ->values();
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
