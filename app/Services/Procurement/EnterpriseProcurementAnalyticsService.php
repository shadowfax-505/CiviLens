<?php

namespace App\Services\Procurement;

use App\Models\Award;
use App\Models\BidSubmission;
use App\Models\Contract;
use App\Models\Tender;
use App\Models\VariationOrder;
use Illuminate\Support\Carbon;

class EnterpriseProcurementAnalyticsService
{
    /**
     * @return array<string, float|int|array<string, int>>
     */
    public function metrics(): array
    {
        $tenders = Tender::query()->withCount('bidSubmissions')->get();
        $contracts = Contract::query()->get();

        return [
            'tender_participation' => BidSubmission::query()->count(),
            'award_distribution' => Award::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'average_bidders' => round((float) $tenders->avg('bid_submissions_count'), 2),
            'single_bid_tenders' => $tenders->where('bid_submissions_count', 1)->count(),
            'repeat_winners' => Award::query()
                ->join('bid_submissions', 'awards.bid_submission_id', '=', 'bid_submissions.id')
                ->selectRaw('bid_submissions.bidder_organization_id, count(*) as total')
                ->groupBy('bid_submissions.bidder_organization_id')
                ->havingRaw('count(*) > 1')
                ->count(),
            'procurement_duration_days' => $this->averageDurationDays(),
            'bid_competitiveness' => round((float) $tenders->avg('bid_submissions_count'), 2),
            'contract_completion_rate' => $contracts->count() > 0 ? round(($contracts->where('status', 'closed')->count() / $contracts->count()) * 100, 2) : 0.0,
            'variation_frequency' => $contracts->count() > 0 ? round(VariationOrder::query()->where('status', 'approved')->count() / $contracts->count(), 2) : 0.0,
        ];
    }

    private function averageDurationDays(): float
    {
        $durations = Tender::query()
            ->whereNotNull('published_at')
            ->whereNotNull('closed_at')
            ->get()
            ->map(fn (Tender $tender): float => Carbon::parse($tender->published_at)->diffInDays($tender->closed_at));

        return round((float) $durations->avg(), 2);
    }
}
