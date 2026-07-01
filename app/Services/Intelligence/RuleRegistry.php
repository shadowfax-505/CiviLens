<?php

namespace App\Services\Intelligence;

use App\Models\IntelligenceRule;
use Illuminate\Support\Collection;

class RuleRegistry
{
    /**
     * @return Collection<int, IntelligenceRule>
     */
    public function enabled(): Collection
    {
        return IntelligenceRule::query()
            ->with('type')
            ->where('is_active', true)
            ->orderBy('module')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function categories(): array
    {
        return [
            'project-delay-risk',
            'budget-overrun-risk',
            'low-budget-utilization',
            'procurement-single-bid-risk',
            'contractor-compliance-expiry',
            'contractor-blacklist-signal',
            'document-missing-metadata',
            'document-pending-ocr-readiness',
            'search-indexing-failure',
            'analytics-alert-escalation',
        ];
    }
}
