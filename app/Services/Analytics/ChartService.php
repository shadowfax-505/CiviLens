<?php

namespace App\Services\Analytics;

use App\Support\Analytics\AnalyticsFilters;
use App\Support\Analytics\ChartDefinition;

class ChartService
{
    public function __construct(private readonly TrendAnalysisService $trends) {}

    /**
     * @param  array<string, array<string, mixed>>  $metrics
     * @return array<string, array<string, mixed>>
     */
    public function dashboard(string $dashboard, array $metrics, AnalyticsFilters $filters): array
    {
        $budgetUtilization = (float) ($metrics['finance.budget_utilization']['value'] ?? 0);
        $remaining = (float) ($metrics['finance.remaining_budget']['value'] ?? 0);
        $spent = (float) ($metrics['finance.spent_budget']['value'] ?? 0);
        $statusDistribution = $this->trends->projectStatusDistribution($filters);
        $investmentByDistrict = $this->trends->investmentByDistrict($filters);
        $monthlySpending = $this->trends->monthlySpending($filters);

        return [
            'budget_utilization' => (new ChartDefinition(
                key: 'budget_utilization',
                title: 'Budget Utilization',
                type: 'doughnut',
                labels: ['Spent', 'Remaining'],
                datasets: [[
                    'label' => 'Budget',
                    'data' => [$spent, max($remaining, 0)],
                    'backgroundColor' => ['#2563eb', '#dbeafe'],
                ]],
            ))->toArray(),
            'project_status_distribution' => (new ChartDefinition(
                key: 'project_status_distribution',
                title: 'Project Status Distribution',
                type: 'bar',
                labels: array_keys($statusDistribution),
                datasets: [[
                    'label' => 'Projects',
                    'data' => array_values($statusDistribution),
                    'backgroundColor' => '#0f766e',
                ]],
            ))->toArray(),
            'investment_by_district' => (new ChartDefinition(
                key: 'investment_by_district',
                title: 'Investment by District',
                type: 'bar',
                labels: array_keys($investmentByDistrict),
                datasets: [[
                    'label' => 'Budget Allocation',
                    'data' => array_values($investmentByDistrict),
                    'backgroundColor' => '#ea580c',
                ]],
            ))->toArray(),
            'monthly_spending' => (new ChartDefinition(
                key: 'monthly_spending',
                title: 'Monthly Spending Trend',
                type: 'line',
                labels: array_keys($monthlySpending),
                datasets: [[
                    'label' => 'Spending',
                    'data' => array_values($monthlySpending),
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.16)',
                    'fill' => true,
                ]],
                options: ['budget_utilization' => $budgetUtilization, 'dashboard' => $dashboard],
            ))->toArray(),
        ];
    }
}
