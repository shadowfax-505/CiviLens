<?php

namespace App\Services\Extraction;

use App\Data\Extraction\RecognizedWord;

class FieldValueMatcher
{
    /**
     * Decide whether recognized text supports a gold field value.
     *
     * Normalization is deliberately conservative: case folding, Unicode
     * whitespace collapsing, and Bengali-to-ASCII digit folding, and nothing
     * else. Anything more aggressive — stripping punctuation, fuzzy edit
     * distance — would manufacture agreement that the engine did not earn, and
     * the calibration guarantee is only as honest as this comparison.
     */
    public function matches(string $goldValue, string $recognizedText): bool
    {
        $gold = $this->normalize($goldValue);

        if ($gold === '') {
            return false;
        }

        return str_contains($this->normalize($recognizedText), $gold);
    }

    /**
     * Find the shortest run of recognized words whose text contains the gold
     * value, and return that run's confidences.
     *
     * A per-field score has to come from the words that actually back the field.
     * Scoring every field on a page with the page average ties them all to one
     * value, and conformal calibration can only accept or reject a run of ties
     * whole — so a single page of errors is admitted at the first candidate
     * threshold and no threshold ever satisfies the bound.
     *
     * @param  list<RecognizedWord>  $words
     * @return list<float>|null null when no run of words supports the value
     */
    public function locate(string $goldValue, array $words): ?array
    {
        $gold = $this->normalize($goldValue);

        if ($gold === '' || $words === []) {
            return null;
        }

        $count = count($words);
        $best = null;

        for ($start = 0; $start < $count; $start++) {
            $buffer = '';

            for ($end = $start; $end < $count; $end++) {
                $buffer .= ($end === $start ? '' : ' ').$words[$end]->text;

                if (! str_contains($this->normalize($buffer), $gold)) {
                    continue;
                }

                $span = $end - $start + 1;

                if ($best === null || $span < $best['span']) {
                    $best = ['span' => $span, 'start' => $start, 'end' => $end];
                }

                break;
            }
        }

        if ($best === null) {
            return null;
        }

        $confidences = [];

        for ($index = $best['start']; $index <= $best['end']; $index++) {
            $confidences[] = $words[$index]->confidence;
        }

        return $confidences;
    }

    public function normalize(string $value): string
    {
        $digitsFolded = strtr($value, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ]);

        $collapsed = preg_replace('/\s+/u', ' ', $digitsFolded);

        return mb_strtolower(trim($collapsed ?? $digitsFolded));
    }
}
