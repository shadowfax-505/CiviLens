<?php

namespace App\Services\Contractors;

use App\Models\ComplianceRecord;
use App\Models\ContractorLicense;
use App\Models\ContractorPerformanceSnapshot;
use App\Models\ContractorProfile;
use App\Models\Organization;

class ContractorDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'organizations' => Organization::query()->whereNull('archived_at')->count(),
            'contractors' => ContractorProfile::query()->whereNull('archived_at')->count(),
            'blacklisted' => ContractorProfile::query()->where('is_blacklisted', true)->count(),
            'suspended' => ContractorProfile::query()->where('is_suspended', true)->count(),
            'expiring_licenses' => ContractorLicense::query()->whereDate('expiry_date', '<=', now()->addDays(60))->count(),
            'failed_compliance' => ComplianceRecord::query()->whereHas('status', fn ($query) => $query->where('slug', 'failed'))->count(),
            'average_delay' => round((float) ContractorPerformanceSnapshot::query()->avg('delay_days'), 2),
            'average_evaluation' => round((float) ContractorPerformanceSnapshot::query()->avg('agency_evaluation'), 2),
        ];
    }
}
