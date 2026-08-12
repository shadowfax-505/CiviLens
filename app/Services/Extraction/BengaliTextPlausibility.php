<?php

namespace App\Services\Extraction;

/**
 * Decide whether Bengali text decodes to the characters the page actually shows.
 *
 * Bangladeshi government PDFs are frequently typeset in legacy fonts whose bytes
 * are mapped as though they were Unicode. The result carries a text layer, reads
 * as Bengali to any encoding check, and decodes to the wrong characters: a page
 * whose heading is "সূচিপত্র" extracts as "সূডি ত্র". Density-based routing sees
 * a dense text layer and trusts it, so the page is never sent to OCR and the
 * wrong characters are what everything downstream reads.
 *
 * The signal is orphaned vowel signs. A dependent vowel sign in Bengali always
 * follows a consonant; in a mis-mapped font it lands anywhere. Measured on this
 * corpus, across 226 pages:
 *
 *   native text layer   median 0.3516 orphaned, max 0.5032
 *   OCR of the same     median 0.0316 orphaned, max 0.0747
 *
 * The two populations do not overlap, and the default threshold sits in the gap
 * between them rather than at a value chosen by taste.
 */
class BengaliTextPlausibility
{
    /** Consonants and the virama, which a dependent vowel sign may legitimately follow. */
    private const PRECEDES_VOWEL = '\x{0995}-\x{09B9}\x{09DC}-\x{09DF}\x{09CD}';

    private const VOWEL_SIGNS = '\x{09BE}-\x{09CC}\x{09D7}';

    /**
     * Whether the text is too damaged to treat as a usable text layer.
     */
    public function isImplausible(string $text): bool
    {
        $rate = $this->orphanedVowelRate($text);

        if ($rate === null) {
            return false;
        }

        return $rate > (float) config('civiclens.extraction.bengali_orphan_vowel_threshold', 0.15);
    }

    /**
     * The share of dependent vowel signs that do not follow a consonant.
     *
     * Null when there is too little Bengali to judge. Flagging a page on three
     * vowel signs would reject English pages carrying a stray character, and a
     * page routed to OCR for no reason costs both accuracy and time.
     */
    public function orphanedVowelRate(string $text): ?float
    {
        $signs = preg_match_all('/['.self::VOWEL_SIGNS.']/u', $text);

        if ($signs === false || $signs < (int) config('civiclens.extraction.bengali_minimum_vowel_signs', 20)) {
            return null;
        }

        $orphaned = preg_match_all('/(?<!['.self::PRECEDES_VOWEL.'])['.self::VOWEL_SIGNS.']/u', $text);

        if ($orphaned === false) {
            return null;
        }

        return round($orphaned / $signs, 4);
    }
}
