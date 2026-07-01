<?php

namespace App\Services\Intelligence;

use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceProcessingJob;
use App\Models\IntelligenceRule;
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
            ],
            'charts' => [
                'status_distribution' => $statusDistribution,
                'severity_distribution' => $severityDistribution,
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
