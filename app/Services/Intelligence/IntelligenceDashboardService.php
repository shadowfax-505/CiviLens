<?php

namespace App\Services\Intelligence;

use App\Models\Agency;
use App\Models\Budget;
use App\Models\CitizenReport;
use App\Models\CivicIntelligenceRun;
use App\Models\Document;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceProcessingJob;
use App\Models\IntelligenceRule;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IntelligenceDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $statusDistribution = IntelligenceIndicator::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (int|string $value): int => (int) $value)
            ->all();

        $severityDistribution = IntelligenceIndicator::query()
            ->selectRaw('severity, count(*) as aggregate')
            ->groupBy('severity')
            ->pluck('aggregate', 'severity')
            ->map(fn (int|string $value): int => (int) $value)
            ->all();

        return [
            'cards' => [
                'pending_reviews' => IntelligenceIndicator::query()->where('status', 'pending')->count(),
                'high_risk_signals' => IntelligenceIndicator::query()->where('severity', 'critical')->count(),
                'stale_evidence' => IntelligenceIndicator::query()->where('status', 'needs_more_evidence')->count(),
                'ocr_readiness' => IntelligenceProcessingJob::query()->where('job_type', 'ocr_preparation')->where('status', 'queued')->count(),
                'active_rules' => IntelligenceRule::query()->where('is_active', true)->count(),
                'engine_runs' => CivicIntelligenceRun::query()->count(),
            ],
            'charts' => [
                'status_distribution' => $statusDistribution,
                'severity_distribution' => $severityDistribution,
                'module_distribution' => IntelligenceIndicator::query()
                    ->selectRaw('module, count(*) as aggregate')
                    ->groupBy('module')
                    ->pluck('aggregate', 'module')
                    ->map(fn (int|string $value): int => (int) $value)
                    ->all(),
            ],
            'recent_indicators' => IntelligenceIndicator::query()
                ->with('rule')
                ->latest('detected_at')
                ->limit(8)
                ->get(),
            'recent_jobs' => IntelligenceProcessingJob::query()
                ->latest('queued_at')
                ->limit(8)
                ->get(),
            'latest_engine_run' => CivicIntelligenceRun::query()
                ->latest('started_at')
                ->first(),
            'integrity_timeline' => CivicIntelligenceRun::query()
                ->latest('started_at')
                ->limit(10)
                ->get(),
            'rule_execution_history' => IntelligenceRule::query()
                ->withCount('indicators')
                ->orderByDesc('last_executed_at')
                ->orderBy('priority')
                ->limit(10)
                ->get(),
            'rankings' => [
                'agencies' => Agency::query()->withCount(['projects', 'tenders'])->orderByDesc('projects_count')->limit(5)->get(),
                'contractors' => Organization::query()->orderBy('legal_name')->limit(5)->get(),
                'projects' => Project::query()->orderByDesc('progress_percentage')->limit(5)->get(),
                'budgets' => Budget::query()->orderByDesc('actual_expenditure')->limit(5)->get(),
                'documents' => [
                    'complete' => Document::query()->whereNotNull('description')->whereNotNull('language')->count(),
                    'metadata_gaps' => Document::query()->where(fn ($query) => $query->whereNull('description')->orWhereNull('language'))->count(),
                ],
                'citizen_reports' => CitizenReport::query()
                    ->whereNotNull('project_id')
                    ->selectRaw('project_id, count(*) as aggregate')
                    ->groupBy('project_id')
                    ->orderByDesc('aggregate')
                    ->limit(5)
                    ->get(),
                'geography' => Project::query()
                    ->whereNotNull('division_id')
                    ->selectRaw('division_id, count(*) as aggregate')
                    ->groupBy('division_id')
                    ->orderByDesc('aggregate')
                    ->limit(5)
                    ->get(),
            ],
            'performance' => [
                'average_run_ms' => (int) CivicIntelligenceRun::query()->whereNotNull('summary_payload')->get()->avg(fn (CivicIntelligenceRun $run): int => (int) data_get($run->summary_payload, 'duration_ms', 0)),
                'rules_with_execution_data' => IntelligenceRule::query()->whereNotNull('last_executed_at')->count(),
                'failed_runs_24h' => CivicIntelligenceRun::query()->where('status', 'failed')->where('started_at', '>=', now()->subDay())->count(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, IntelligenceIndicator>
     */
    public function indicators(array $filters): LengthAwarePaginator
    {
        return IntelligenceIndicator::query()
            ->with(['rule', 'evidence'])
            ->when($filters['q'] ?? null, function ($query, string $q): void {
                $query->where(function ($nested) use ($q): void {
                    $nested->where('title', 'like', '%'.$q.'%')
                        ->orWhere('description', 'like', '%'.$q.'%')
                        ->orWhere('module', 'like', '%'.$q.'%');
                });
            })
            ->when($filters['severity'] ?? null, fn ($query, string $severity) => $query->where('severity', $severity))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['module'] ?? null, fn ($query, string $module) => $query->where('module', $module))
            ->latest('detected_at')
            ->paginate(15)
            ->withQueryString();
    }
}
