<?php

namespace App\Services\Analytics;

use App\Models\Agency;
use App\Models\Contract;
use App\Models\ContractorLicense;
use App\Models\ContractorPerformanceSnapshot;
use App\Models\ContractorProfile;
use App\Models\District;
use App\Models\Document;
use App\Models\SearchClick;
use App\Models\SearchHistory;
use App\Models\User;
use App\Support\Analytics\AnalyticsFilters;
use App\Support\Analytics\MetricResult;
use Illuminate\Support\Facades\DB;

class MetricRegistry
{
    public function __construct(private readonly AggregationEngine $aggregates) {}

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->definitions());
    }

    public function calculate(string $key, AnalyticsFilters $filters): MetricResult
    {
        $definition = $this->definitions()[$key] ?? null;

        if ($definition === null) {
            abort(404, 'Metric not registered.');
        }

        $value = $definition['resolver']($filters);

        return new MetricResult(
            key: $key,
            label: $definition['label'],
            category: $definition['category'],
            value: is_float($value) ? round($value, 2) : $value,
            unit: $definition['unit'] ?? null,
            description: $definition['description'] ?? null,
            meta: $definition['meta'] ?? [],
        );
    }

    /**
     * @return list<string>
     */
    public function category(string $category): array
    {
        return array_keys(array_filter($this->definitions(), fn (array $definition): bool => $definition['category'] === $category));
    }

    /**
     * @return list<string>
     */
    public function dashboardMetrics(string $dashboard): array
    {
        return match ($dashboard) {
            'finance' => $this->category('finance'),
            'procurement' => $this->category('procurement'),
            'contractors' => $this->category('contractors'),
            'agency' => ['agencies.total', 'projects.active', 'projects.delayed', 'finance.total_budget', 'procurement.active_tenders'],
            'projects' => $this->category('projects'),
            'search' => $this->category('search'),
            'system' => $this->category('platform'),
            default => [
                'projects.active',
                'projects.completed',
                'projects.delayed',
                'finance.budget_utilization',
                'finance.total_budget',
                'finance.spent_budget',
                'finance.remaining_budget',
                'procurement.active_tenders',
                'contractors.blacklisted',
                'documents.total',
                'search.total_searches',
                'users.active',
                'agencies.total',
            ],
        };
    }

    /**
     * @return array<string, array{label: string, category: string, resolver: callable(AnalyticsFilters): mixed, unit?: string, description?: string, meta?: array<string, mixed>}>
     */
    private function definitions(): array
    {
        return [
            'projects.active' => [
                'label' => 'Active Projects',
                'category' => 'projects',
                'resolver' => fn (AnalyticsFilters $filters): int => $this->aggregates->projects($filters)->where('is_active', true)->count(),
            ],
            'projects.completed' => [
                'label' => 'Completed Projects',
                'category' => 'projects',
                'resolver' => fn (AnalyticsFilters $filters): int => $this->aggregates->projects($filters)
                    ->where(fn ($query) => $query->whereNotNull('actual_end_date')->orWhereHas('status', fn ($status) => $status->where('slug', 'completed')))
                    ->count(),
            ],
            'projects.delayed' => [
                'label' => 'Delayed Projects',
                'category' => 'projects',
                'resolver' => fn (AnalyticsFilters $filters): int => $this->aggregates->projects($filters)
                    ->whereNull('actual_end_date')
                    ->whereDate('planned_end_date', '<', now()->toDateString())
                    ->where('progress_percentage', '<', 100)
                    ->count(),
            ],
            'projects.average_progress' => [
                'label' => 'Average Progress',
                'category' => 'projects',
                'unit' => '%',
                'resolver' => fn (AnalyticsFilters $filters): float => (float) $this->aggregates->projects($filters)->avg('progress_percentage'),
            ],
            'finance.total_budget' => [
                'label' => 'Total Budget',
                'category' => 'finance',
                'unit' => 'BDT',
                'resolver' => fn (AnalyticsFilters $filters): float => (float) $this->aggregates->budgets($filters)->sum('current_allocation'),
            ],
            'finance.spent_budget' => [
                'label' => 'Spent Budget',
                'category' => 'finance',
                'unit' => 'BDT',
                'resolver' => fn (AnalyticsFilters $filters): float => (float) $this->aggregates->budgets($filters)->sum('actual_expenditure'),
            ],
            'finance.remaining_budget' => [
                'label' => 'Remaining Budget',
                'category' => 'finance',
                'unit' => 'BDT',
                'resolver' => fn (AnalyticsFilters $filters): float => (float) $this->aggregates->budgets($filters)->sum(DB::raw('current_allocation - reserved_amount - committed_amount - actual_expenditure')),
            ],
            'finance.budget_utilization' => [
                'label' => 'Budget Utilization',
                'category' => 'finance',
                'unit' => '%',
                'resolver' => function (AnalyticsFilters $filters): float {
                    $budget = $this->aggregates->budgets($filters);
                    $allocation = (float) $budget->clone()->sum('current_allocation');
                    $spent = (float) $budget->clone()->sum('actual_expenditure');

                    return $allocation > 0 ? ($spent / $allocation) * 100 : 0.0;
                },
            ],
            'finance.reserve_ratio' => [
                'label' => 'Reserve Ratio',
                'category' => 'finance',
                'unit' => '%',
                'resolver' => function (AnalyticsFilters $filters): float {
                    $budget = $this->aggregates->budgets($filters);
                    $allocation = (float) $budget->clone()->sum('current_allocation');
                    $reserved = (float) $budget->clone()->sum('reserved_amount');

                    return $allocation > 0 ? ($reserved / $allocation) * 100 : 0.0;
                },
            ],
            'procurement.active_tenders' => [
                'label' => 'Active Tenders',
                'category' => 'procurement',
                'resolver' => fn (AnalyticsFilters $filters): int => $this->aggregates->tenders($filters)->whereNull('archived_at')->whereNull('closed_at')->count(),
            ],
            'procurement.closed_tenders' => [
                'label' => 'Closed Tenders',
                'category' => 'procurement',
                'resolver' => fn (AnalyticsFilters $filters): int => $this->aggregates->tenders($filters)->whereNotNull('closed_at')->count(),
            ],
            'procurement.award_rate' => [
                'label' => 'Award Rate',
                'category' => 'procurement',
                'unit' => '%',
                'resolver' => function (AnalyticsFilters $filters): float {
                    $total = $this->aggregates->tenders($filters)->count();
                    $awarded = $this->aggregates->tenders($filters)->whereHas('awards')->count();

                    return $total > 0 ? ($awarded / $total) * 100 : 0.0;
                },
            ],
            'procurement.contracts' => [
                'label' => 'Contracts',
                'category' => 'procurement',
                'resolver' => fn (): int => Contract::query()->count(),
            ],
            'contractors.blacklisted' => [
                'label' => 'Blacklisted Contractors',
                'category' => 'contractors',
                'resolver' => fn (): int => ContractorProfile::query()->where('is_blacklisted', true)->count(),
            ],
            'contractors.suspended' => [
                'label' => 'Suspended Contractors',
                'category' => 'contractors',
                'resolver' => fn (): int => ContractorProfile::query()->where('is_suspended', true)->count(),
            ],
            'contractors.expiring_licenses' => [
                'label' => 'Expiring Licenses',
                'category' => 'contractors',
                'resolver' => fn (): int => ContractorLicense::query()->whereDate('expiry_date', '<=', now()->addDays(60)->toDateString())->count(),
            ],
            'contractors.average_delay' => [
                'label' => 'Average Delay',
                'category' => 'contractors',
                'unit' => 'days',
                'resolver' => fn (): float => (float) ContractorPerformanceSnapshot::query()->avg('delay_days'),
            ],
            'documents.total' => [
                'label' => 'Documents',
                'category' => 'documents',
                'resolver' => fn (): int => Document::query()->whereNull('archived_at')->count(),
            ],
            'documents.pending_ocr' => [
                'label' => 'Pending OCR',
                'category' => 'documents',
                'resolver' => fn (): int => Document::query()->where('ocr_status', 'pending')->count(),
            ],
            'documents.storage_usage' => [
                'label' => 'Storage Usage',
                'category' => 'documents',
                'unit' => 'bytes',
                'resolver' => fn (): int => (int) Document::query()->sum('file_size'),
            ],
            'search.total_searches' => [
                'label' => 'Total Searches',
                'category' => 'search',
                'resolver' => fn (): int => SearchHistory::query()->count(),
            ],
            'search.failed_searches' => [
                'label' => 'Failed Searches',
                'category' => 'search',
                'resolver' => fn (): int => SearchHistory::query()->where('successful', false)->count(),
            ],
            'search.average_latency' => [
                'label' => 'Average Search Latency',
                'category' => 'search',
                'unit' => 'ms',
                'resolver' => fn (): float => (float) SearchHistory::query()->avg('latency_ms'),
            ],
            'search.clicks' => [
                'label' => 'Search Clicks',
                'category' => 'search',
                'resolver' => fn (): int => SearchClick::query()->count(),
            ],
            'users.active' => [
                'label' => 'Active Users',
                'category' => 'users',
                'resolver' => fn (): int => User::query()->where('is_active', true)->count(),
            ],
            'users.total' => [
                'label' => 'Total Users',
                'category' => 'users',
                'resolver' => fn (): int => User::query()->count(),
            ],
            'agencies.total' => [
                'label' => 'Agencies',
                'category' => 'agencies',
                'resolver' => fn (): int => Agency::query()->count(),
            ],
            'geography.districts' => [
                'label' => 'Districts',
                'category' => 'geography',
                'resolver' => fn (): int => District::query()->count(),
            ],
            'platform.queue_jobs' => [
                'label' => 'Queued Jobs',
                'category' => 'platform',
                'resolver' => fn (): int => DB::table('jobs')->count(),
            ],
            'future_ai.ocr_readiness' => [
                'label' => 'OCR Readiness',
                'category' => 'future_ai',
                'unit' => '%',
                'resolver' => function (): float {
                    $total = Document::query()->count();
                    $prepared = Document::query()->whereNotNull('ocr_status')->count();

                    return $total > 0 ? ($prepared / $total) * 100 : 0.0;
                },
            ],
        ];
    }
}
