<?php

namespace App\Services\Procurement;

use App\Models\Award;
use App\Models\Contract;
use App\Models\Tender;
use Illuminate\Support\Carbon;

class ProcurementDashboardService
{
    public function __construct(private readonly EnterpriseProcurementAnalyticsService $enterpriseAnalytics) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $activeTenders = Tender::query()->whereNull('archived_at')->whereNull('closed_at')->count();
        $closedTenders = Tender::query()->whereNotNull('closed_at')->count();
        $tenderCount = Tender::query()->count();
        $awardCount = Award::query()->count();

        return [
            'active_tenders' => $activeTenders,
            'closed_tenders' => $closedTenders,
            'average_bidders' => round((float) Tender::query()->withCount('bidSubmissions')->get()->avg('bid_submissions_count'), 2),
            'average_evaluation_days' => $this->averageEvaluationDays(),
            'award_rate' => $tenderCount > 0 ? round(($awardCount / $tenderCount) * 100, 2) : 0.0,
            'contract_status_summary' => Contract::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all(),
            'enterprise_metrics' => $this->enterpriseAnalytics->metrics(),
        ];
    }

    private function averageEvaluationDays(): float
    {
        $durations = Award::query()
            ->with('tender')
            ->whereNotNull('awarded_at')
            ->get()
            ->filter(function (Award $award): bool {
                $tender = $award->tender;

                return $tender instanceof Tender && $tender->closed_at !== null;
            })
            ->map(function (Award $award): float {
                $tender = $award->tender;
                if (! $tender instanceof Tender) {
                    return 0.0;
                }

                return Carbon::parse($tender->closed_at)->diffInDays($award->awarded_at, false);
            });

        return round((float) $durations->avg(), 2);
    }
}
