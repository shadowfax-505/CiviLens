<?php

namespace App\Services\Intelligence;

use App\Models\IntelligenceIndicator;

class ExplainabilityService
{
    /**
     * @return array<string, mixed>
     */
    public function explain(IntelligenceIndicator $indicator): array
    {
        $indicator->loadMissing(['rule.type', 'evidence', 'reviews.reviewer']);

        return [
            'indicator' => $indicator,
            'rule' => $indicator->rule,
            'rule_version' => $indicator->rule_version,
            'detected_at' => $indicator->detected_at,
            'confidence_score' => $indicator->confidence_score,
            'severity' => $indicator->severity,
            'status' => $indicator->status,
            'evidence' => $indicator->evidence,
            'reviews' => $indicator->reviews,
        ];
    }
}
