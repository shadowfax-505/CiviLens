<?php

namespace App\Services\Intelligence;

use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;

class ExplainabilityService
{
    /**
     * @return array<string, mixed>
     */
    public function explain(IntelligenceIndicator $indicator): array
    {
        $indicator->loadMissing(['rule.type', 'evidence.evidenceable', 'reviews.reviewer', 'source']);
        $payload = is_array($indicator->detection_payload) ? $indicator->detection_payload : [];
        $metadata = is_array($indicator->metadata) ? $indicator->metadata : [];
        $rule = $indicator->rule;
        $rule = $rule instanceof IntelligenceRule ? $rule : null;
        $thresholds = is_array($rule?->thresholds) ? $rule->thresholds : [];

        return [
            'indicator' => $indicator,
            'rule' => $rule,
            'triggered_rule' => $rule?->name,
            'rule_version' => $indicator->rule_version,
            'detected_at' => $indicator->detected_at,
            'confidence_score' => $indicator->confidence_score,
            'severity' => $indicator->severity,
            'status' => $indicator->status,
            'threshold' => $thresholds,
            'actual_value' => data_get($payload, 'actual_value', data_get($payload, 'value', data_get($payload, 'award_count', data_get($payload, 'report_count')))),
            'expected_value' => data_get($payload, 'expected_value', data_get($thresholds, 'warning')),
            'engine_version' => data_get($metadata, 'engine_version', 'rule-only'),
            'engine_run_id' => data_get($metadata, 'engine_run_id'),
            'recommendation' => data_get($payload, 'recommendation', 'Review the linked source records and evidence before accepting or dismissing this indicator.'),
            'calculation_timestamp' => $indicator->detected_at,
            'human_review_required' => true,
            'source_record' => $indicator->source,
            'evidence' => $indicator->evidence,
            'reviews' => $indicator->reviews,
        ];
    }
}
