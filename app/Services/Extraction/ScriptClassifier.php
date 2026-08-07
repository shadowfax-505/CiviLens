<?php

namespace App\Services\Extraction;

class ScriptClassifier
{
    private const DOMINANCE = 0.85;

    /**
     * Classify a page as Bengali, Latin, mixed, or unknown.
     *
     * Group-conditional calibration partitions on this value, so it must be
     * deterministic and independent of extraction confidence — a page's script
     * is a property of the document, not of how well the engine read it.
     */
    public function classify(string $text): string
    {
        $bengali = preg_match_all('/[\x{0980}-\x{09FF}]/u', $text);
        $latin = preg_match_all('/[A-Za-z]/u', $text);

        if ($bengali === false || $latin === false) {
            return 'unknown';
        }

        $total = $bengali + $latin;

        if ($total === 0) {
            return 'unknown';
        }

        if ($bengali / $total >= self::DOMINANCE) {
            return 'bn';
        }

        if ($latin / $total >= self::DOMINANCE) {
            return 'en';
        }

        return 'mixed';
    }
}
