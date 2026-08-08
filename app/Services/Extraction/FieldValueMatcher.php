<?php

namespace App\Services\Extraction;

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
