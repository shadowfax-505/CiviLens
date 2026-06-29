<?php

namespace App\Services\Contractors;

use App\Models\ContractorProfile;

class ContractorScoreService
{
    /**
     * @return array<string, float|int>
     */
    public function calculate(ContractorProfile $profile): array
    {
        $complianceScore = $this->complianceScore($profile);
        $deliveryScore = $this->deliveryScore($profile);
        $financialScore = $this->financialScore($profile);
        $qualityScore = $this->qualityScore($profile);
        $experienceScore = min(100.0, (float) $profile->performanceSnapshots()->count() * 10);
        $successRate = $this->contractSuccessRate($profile);

        $overall = round(($complianceScore + $deliveryScore + $financialScore + $qualityScore + $experienceScore + $successRate) / 6, 2);

        return [
            'overall_contractor_score' => $overall,
            'risk_score' => round(100 - $overall, 2),
            'compliance_score' => $complianceScore,
            'delivery_score' => $deliveryScore,
            'financial_score' => $financialScore,
            'quality_score' => $qualityScore,
            'experience_score' => $experienceScore,
            'contract_success_rate' => $successRate,
            'average_delay' => round((float) $profile->performanceSnapshots()->avg('delay_days'), 2),
            'average_budget_variance' => round((float) $profile->performanceSnapshots()->avg('cost_variance'), 2),
            'average_evaluation' => round((float) $profile->performanceSnapshots()->avg('agency_evaluation'), 2),
            'active_projects' => $profile->performanceSnapshots()->where('completion_status', 'active')->count(),
            'completed_projects' => $profile->performanceSnapshots()->where('completion_status', 'completed')->count(),
            'historical_awards' => $profile->performanceSnapshots()->count(),
        ];
    }

    private function complianceScore(ContractorProfile $profile): float
    {
        $total = $profile->complianceRecords()->count();

        if ($total === 0) {
            return 100.0;
        }

        $compliant = $profile->complianceRecords()
            ->whereHas('status', fn ($query) => $query->where('slug', 'compliant'))
            ->count();

        return round(($compliant / $total) * 100, 2);
    }

    private function deliveryScore(ContractorProfile $profile): float
    {
        $averageDelay = (float) $profile->performanceSnapshots()->avg('delay_days');

        return max(0.0, round(100 - min(100, $averageDelay), 2));
    }

    private function financialScore(ContractorProfile $profile): float
    {
        $averageVariance = abs((float) $profile->performanceSnapshots()->avg('cost_variance'));

        return max(0.0, round(100 - min(100, $averageVariance), 2));
    }

    private function qualityScore(ContractorProfile $profile): float
    {
        $quality = (float) $profile->performanceSnapshots()->avg('quality_rating');
        $evaluation = (float) $profile->performanceSnapshots()->avg('agency_evaluation');

        if ($quality === 0.0 && $evaluation === 0.0) {
            return 100.0;
        }

        return round(($quality + $evaluation) / 2, 2);
    }

    private function contractSuccessRate(ContractorProfile $profile): float
    {
        $total = $profile->performanceSnapshots()->count();

        if ($total === 0) {
            return 100.0;
        }

        $successful = $profile->performanceSnapshots()->where('completion_status', 'completed')->count();

        return round(($successful / $total) * 100, 2);
    }
}
