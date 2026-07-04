<?php

namespace App\Services\Executive;

use App\Models\Agency;
use App\Models\AnalyticsAlert;
use App\Models\Budget;
use App\Models\CitizenReport;
use App\Models\CivicIntelligenceRun;
use App\Models\Document;
use App\Models\IntelligenceIndicator;
use App\Models\Organization;
use App\Models\Project;
use App\Models\SearchHistory;
use App\Models\Tender;
use App\Services\Operations\SystemMetricsService;

class ExecutiveDashboardService
{
    public function __construct(private readonly SystemMetricsService $systems) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $criticalSignals = IntelligenceIndicator::query()->where('severity', 'critical')->count();
        $warningSignals = IntelligenceIndicator::query()->where('severity', 'warning')->count();

        return [
            'cards' => [
                ['label' => 'Projects', 'value' => Project::query()->count(), 'detail' => 'Active civic delivery records'],
                ['label' => 'Budgets', 'value' => $this->money(Budget::query()->sum('current_allocation')), 'detail' => 'Current allocation tracked'],
                ['label' => 'Procurement', 'value' => Tender::query()->count(), 'detail' => 'Tender lifecycle records'],
                ['label' => 'Contractors', 'value' => Organization::query()->count(), 'detail' => 'Vendor intelligence records'],
                ['label' => 'Citizen Reports', 'value' => CitizenReport::query()->count(), 'detail' => 'Moderated public submissions'],
                ['label' => 'Documents', 'value' => Document::query()->count(), 'detail' => 'Controlled evidence records'],
                ['label' => 'Integrity Engine', 'value' => CivicIntelligenceRun::query()->count(), 'detail' => 'Deterministic run history'],
                ['label' => 'Search Analytics', 'value' => SearchHistory::query()->count(), 'detail' => 'Discovery interactions'],
            ],
            'risk_summary' => [
                'critical' => $criticalSignals,
                'warning' => $warningSignals,
                'pending_review' => IntelligenceIndicator::query()->where('status', 'pending')->count(),
            ],
            'system_health' => $this->systems->health(),
            'recent_integrity_runs' => CivicIntelligenceRun::query()
                ->latest('started_at')
                ->limit(5)
                ->get(),
            'recent_alerts' => AnalyticsAlert::query()
                ->latest('triggered_at')
                ->limit(5)
                ->get(),
            'top_agencies' => Agency::query()
                ->withCount(['projects', 'tenders'])
                ->orderByDesc('projects_count')
                ->orderBy('name')
                ->limit(5)
                ->get(),
            'top_contractors' => Organization::query()
                ->orderBy('legal_name')
                ->limit(5)
                ->get(),
            'charts' => [
                [
                    'title' => 'Risk Breakdown',
                    'segments' => [
                        ['label' => 'Critical', 'value' => $criticalSignals],
                        ['label' => 'Warning', 'value' => $warningSignals],
                        ['label' => 'Pending Review', 'value' => IntelligenceIndicator::query()->where('status', 'pending')->count()],
                    ],
                ],
                [
                    'title' => 'Civic Data Coverage',
                    'segments' => [
                        ['label' => 'Projects', 'value' => Project::query()->count()],
                        ['label' => 'Documents', 'value' => Document::query()->count()],
                        ['label' => 'Citizen Reports', 'value' => CitizenReport::query()->count()],
                    ],
                ],
            ],
            'generated_at' => date(DATE_ATOM),
        ];
    }

    private function money(int|float|string|null $value): string
    {
        return number_format((float) $value, 0);
    }
}
