<?php

namespace App\Services\Analytics;

use App\Support\Analytics\AnalyticsFilters;

class ForecastPreparationService
{
    /**
     * @return array<string, mixed>
     */
    public function prepare(AnalyticsFilters $filters): array
    {
        return [
            'filters' => $filters->toArray(),
            'features' => [
                'budget_utilization',
                'project_progress',
                'procurement_duration',
                'contractor_delay_history',
                'document_activity',
            ],
            'status' => 'ready_for_future_modeling',
        ];
    }
}
