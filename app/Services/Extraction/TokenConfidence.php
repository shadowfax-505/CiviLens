<?php

namespace App\Services\Extraction;

use App\Models\ExtractionField;
use App\Models\ExtractionPage;

/**
 * How well the recognizer read one value, as opposed to its page.
 *
 * ADR-023 records why the distinction matters: the page mean ranks errors at
 * AUC 0.511, a coin flip, while the token's own confidence separates 3.6% error
 * from 28%. Recomputing it on demand from `recognized_words` made the better
 * quantity the harder one to use, and a stratum nobody can select is a stratum
 * nobody can audit.
 */
class TokenConfidence
{
    /**
     * The best confidence among words carrying this value, or null.
     *
     * Best rather than first: a value appearing twice on a page was read twice,
     * and the crop a reviewer is shown is the clearest instance.
     */
    public function for(ExtractionField $field, ?ExtractionPage $page = null): ?float
    {
        $page ??= $field->page;
        $words = $page?->recognized_words;

        if (! is_array($words) || $words === []) {
            return null;
        }

        $value = trim((string) $field->extracted_value);

        if ($value === '') {
            return null;
        }

        $best = null;

        foreach ($words as $word) {
            $text = trim((string) ($word['t'] ?? ''));

            if ($text === '' || mb_strpos($text, $value) === false) {
                continue;
            }

            $confidence = (float) ($word['c'] ?? 0);

            if ($best === null || $confidence > $best) {
                $best = $confidence;
            }
        }

        return $best;
    }
}
