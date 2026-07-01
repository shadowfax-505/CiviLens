<?php

namespace App\Support\Analytics;

use Illuminate\Support\Arr;

class AnalyticsFilters
{
    public function __construct(
        public readonly ?string $dashboard = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly ?int $fiscalYearId = null,
        public readonly ?int $agencyId = null,
        public readonly ?int $districtId = null,
        public readonly ?int $divisionId = null,
        public readonly ?int $contractorId = null,
        public readonly ?int $projectId = null,
        public readonly ?int $budgetId = null,
        public readonly ?int $fundingSourceId = null,
        public readonly ?int $procurementMethodId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            dashboard: Arr::get($data, 'dashboard'),
            dateFrom: Arr::get($data, 'date_from'),
            dateTo: Arr::get($data, 'date_to'),
            fiscalYearId: self::nullableInt(Arr::get($data, 'fiscal_year_id')),
            agencyId: self::nullableInt(Arr::get($data, 'agency_id')),
            districtId: self::nullableInt(Arr::get($data, 'district_id')),
            divisionId: self::nullableInt(Arr::get($data, 'division_id')),
            contractorId: self::nullableInt(Arr::get($data, 'contractor_id')),
            projectId: self::nullableInt(Arr::get($data, 'project_id')),
            budgetId: self::nullableInt(Arr::get($data, 'budget_id')),
            fundingSourceId: self::nullableInt(Arr::get($data, 'funding_source_id')),
            procurementMethodId: self::nullableInt(Arr::get($data, 'procurement_method_id')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'dashboard' => $this->dashboard,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'fiscal_year_id' => $this->fiscalYearId,
            'agency_id' => $this->agencyId,
            'district_id' => $this->districtId,
            'division_id' => $this->divisionId,
            'contractor_id' => $this->contractorId,
            'project_id' => $this->projectId,
            'budget_id' => $this->budgetId,
            'funding_source_id' => $this->fundingSourceId,
            'procurement_method_id' => $this->procurementMethodId,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function hash(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
