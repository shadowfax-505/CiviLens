<?php

namespace App\Services\Intelligence;

class IndicatorScoringService
{
    /**
     * @param  array<string, mixed>  $thresholds
     * @return array{severity: string, confidence: int}
     */
    public function score(float $value, array $thresholds): array
    {
        $warning = (float) ($thresholds['warning'] ?? 50);
        $critical = (float) ($thresholds['critical'] ?? 90);

        $severity = match (true) {
            $value >= $critical => 'critical',
            $value >= $warning => 'warning',
            default => 'info',
        };

        return [
            'severity' => $severity,
            'confidence' => max(0, min(100, (int) round($value))),
        ];
    }
}
