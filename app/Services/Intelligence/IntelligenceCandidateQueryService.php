<?php

namespace App\Services\Intelligence;

use App\Models\AnalyticsAlert;
use App\Models\Award;
use App\Models\Budget;
use App\Models\CitizenReport;
use App\Models\ComplianceRecord;
use App\Models\Document;
use App\Models\IntelligenceRule;
use App\Models\Project;
use App\Models\SearchJob;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class IntelligenceCandidateQueryService
{
    /** @return Builder<covariant Model> */
    public function for(IntelligenceRule $rule): Builder
    {
        return match ($rule->slug) {
            'project-delay-risk' => $this->apply($rule, Project::query()),
            'budget-overrun-risk', 'low-budget-utilization' => $this->apply($rule, Budget::query()),
            'procurement-single-bid-risk' => $this->apply($rule, Tender::query()),
            'contractor-compliance-expiry' => $this->apply($rule, ComplianceRecord::query()),
            'document-missing-metadata', 'document-pending-ocr-readiness' => $this->apply($rule, Document::query()),
            'search-indexing-failure' => $this->apply($rule, SearchJob::query()),
            'analytics-alert-escalation' => $this->apply($rule, AnalyticsAlert::query()),
            'procurement-repeat-winner-concentration' => $this->apply($rule, Award::query()),
            'citizen-report-cluster' => $this->apply($rule, CitizenReport::query()),
            default => $this->apply($rule, Project::query()),
        };
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(IntelligenceRule $rule, Builder $query): Builder
    {
        $thresholds = is_array($rule->thresholds) ? $rule->thresholds : [];

        return match ($rule->slug) {
            'project-delay-risk' => $query
                ->where('planned_end_date', '<', now()->toDateString())
                ->where('progress_percentage', '<', (int) data_get($thresholds, 'max_progress', 90)),
            'budget-overrun-risk' => $query->whereColumn('actual_expenditure', '>', 'current_allocation'),
            'low-budget-utilization' => $query
                ->where('current_allocation', '>', 0)
                ->whereRaw('actual_expenditure * 100 <= current_allocation * ?', [(float) data_get($thresholds, 'max_utilization', 10)]),
            'procurement-single-bid-risk' => $query->has('bidSubmissions', '=', 1)->withCount('bidSubmissions'),
            'contractor-compliance-expiry' => $query->where('next_review_date', '<=', now()->addDays(30)->toDateString()),
            'document-missing-metadata' => $query->where(fn (Builder $query): Builder => $query->whereNull('description')->orWhereNull('language')),
            'document-pending-ocr-readiness' => $query->where('ocr_status', 'pending'),
            'search-indexing-failure' => $query->where('status', 'failed'),
            'analytics-alert-escalation' => $query->whereIn('severity', ['warning', 'critical'])->where('status', 'open'),
            'procurement-repeat-winner-concentration' => $query
                ->join('bid_submissions', 'awards.bid_submission_id', '=', 'bid_submissions.id')
                ->where('awards.status', 'approved')
                ->selectRaw('bid_submissions.bidder_organization_id as bidder_id, count(*) as award_count')
                ->groupBy('bid_submissions.bidder_organization_id')
                ->havingRaw('count(*) >= ?', [(int) data_get($thresholds, 'warning', 3)]),
            'citizen-report-cluster' => $query
                ->whereNull('resolved_at')
                ->whereNotNull('project_id')
                ->selectRaw('project_id, count(*) as report_count')
                ->groupBy('project_id')
                ->havingRaw('count(*) >= ?', [(int) data_get($thresholds, 'warning', 3)]),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function count(IntelligenceRule $rule): int
    {
        $query = $this->for($rule);

        if (in_array($rule->slug, ['procurement-repeat-winner-concentration', 'citizen-report-cluster'], true)) {
            return DB::query()->fromSub($query->toBase(), 'grouped_rule_matches')->count();
        }

        return $query->count();
    }
}
