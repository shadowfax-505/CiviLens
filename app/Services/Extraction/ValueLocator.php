<?php

namespace App\Services\Extraction;

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionTableCell;

/**
 * Find where on the page a value was read from.
 *
 * A reviewer shown a dense budget table and asked whether "100.00" matches the
 * page cannot answer: the figure appears a dozen times among hundreds, and
 * finding it is not the question being asked. Pointing at the exact characters
 * is what makes the question answerable in seconds instead of not at all.
 *
 * A value that appears more than once yields more than one box, and all of them
 * are shown. Choosing one arbitrarily would claim a provenance the recognizer
 * never recorded.
 */
class ValueLocator
{
    /**
     * How many words may be joined to make one value.
     *
     * Four was right while every value was a figure: an amount or a date is one
     * word, and four covered a figure the recognizer had split. Court records
     * are not figures. Measured over the corpus:
     *
     *   amount, date            1 word
     *   law report citation     4 words   "54 DLR (AD) 111"
     *   case number             6, up to 7   "Writ Petition No. 8951 of 2025"
     *   statutory reference     7, up to 15  "Section 410 of the Code of Criminal Procedure"
     *
     * So case numbers located 0% of the time and statutory references 0%, not
     * because the geometry was missing but because the search could never span
     * them. Sixteen covers the ninety-fifth percentile of the longest kind with
     * room to spare.
     *
     * Raising it is safe rather than merely tolerable: the inner loop already
     * stops as soon as the joined run grows longer than the value it is looking
     * for, so a short value still examines a short span, and a longer limit
     * cannot make a run match a value it does not equal.
     */
    private const MAX_WORDS_PER_VALUE = 16;

    /**
     * What these documents print after a figure to mark it as money.
     *
     * The amount pattern stops at the last digit, so the value never carries
     * one of these while the recognized word does. Requiring an exact match
     * therefore failed on precisely the figures that are sums of money — 691
     * of 1,703 unlocatable values on the corpus, and a value that cannot be
     * located defers by rule (ADR-019). The effect was to certify section
     * numbers and withhold the amounts.
     *
     * Trailing only, and only these literals. A separator *inside* a number is
     * left exactly as read: one in the wrong place is a hundredfold error and
     * is the misreading the reviewer is being asked about.
     */
    private const CURRENCY_TERMINATORS = ['/-', '/=', '/', ':-'];

    /** Longest terminator, so the search knows when a run can only grow. */
    private const LONGEST_TERMINATOR = 2;

    /**
     * @return list<array{left: int, top: int, right: int, bottom: int}>
     */
    public function locate(ExtractionField $field, ExtractionPage $page): array
    {
        $words = $page->recognized_words;
        $words = is_array($words) ? array_values($words) : [];

        $wanted = $this->normalise((string) $field->extracted_value);
        $cell = $field->tableCell;

        // A cell box is the box of the *cell*, which is bigger than the value
        // in it: it carries the ruling lines, the padding, and — where the grid
        // is imperfect — part of the neighbouring column. Cropping it and
        // re-reading returned things like "৪৭৪.২৫ | ৪৬,৮৩০" for a value of
        // ৪৬,৮৩০.৮৬, and that disagreement deferred the figure.
        //
        // So the cell says *where to look*, and the words say *what to crop*.
        // The value's own characters are found inside the cell's bounds, which
        // keeps the precision of the word boxes and the certainty of being in
        // the right cell. A cell whose words cannot be matched falls back to
        // the cell box, which is still where the value is.
        if ($cell instanceof ExtractionTableCell) {
            $box = [
                'left' => (int) $cell->box_left,
                'top' => (int) $cell->box_top,
                'right' => (int) $cell->box_right,
                'bottom' => (int) $cell->box_bottom,
            ];

            if ($words === [] || $wanted === '') {
                return [$box];
            }

            $inside = $this->search($this->within($words, $box), $wanted);

            return $inside === [] ? [$box] : $inside;
        }

        if ($words === [] || $wanted === '') {
            return [];
        }

        return $this->search($words, $wanted);
    }

    /**
     * The words whose centre falls inside a box, in reading order.
     *
     * Centre rather than any overlap: a word clipped by the edge of a cell
     * belongs to whichever cell holds most of it, and taking every word that
     * merely touches the box would pull the neighbouring column back in.
     *
     * @param  list<array<string, mixed>>  $words
     * @param  array{left: int, top: int, right: int, bottom: int}  $box
     * @return list<array<string, mixed>>
     */
    private function within(array $words, array $box): array
    {
        $inside = [];

        foreach ($words as $word) {
            $left = (int) ($word['l'] ?? 0);
            $top = (int) ($word['y'] ?? 0);
            $centreX = $left + ((int) ($word['w'] ?? 0)) / 2;
            $centreY = $top + ((int) ($word['h'] ?? 0)) / 2;

            if ($centreX >= $box['left'] && $centreX <= $box['right']
                && $centreY >= $box['top'] && $centreY <= $box['bottom']) {
                $inside[] = $word;
            }
        }

        return $inside;
    }

    /**
     * @param  list<array<string, mixed>>  $words
     * @return list<array{left: int, top: int, right: int, bottom: int}>
     */
    private function search(array $words, string $wanted): array
    {
        $found = [];
        $count = count($words);

        for ($start = 0; $start < $count; $start++) {
            $joined = '';

            for ($span = 0; $span < self::MAX_WORDS_PER_VALUE && $start + $span < $count; $span++) {
                $joined .= $this->normalise((string) ($words[$start + $span]['t'] ?? ''));

                if ($this->matches($joined, $wanted)) {
                    $found[] = $this->box(array_slice($words, $start, $span + 1));

                    // Past the words just consumed: a run that matched cannot
                    // also start inside itself.
                    $start += $span;

                    break;
                }

                // Once the run is longer than the value plus the longest thing
                // that may follow it, it can only grow further, so there is
                // nothing left to find from this starting word.
                if (mb_strlen($joined) > mb_strlen($wanted) + self::LONGEST_TERMINATOR) {
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Whether a run of words is the value, allowing for a currency terminator
     * the value itself never carries.
     */
    private function matches(string $joined, string $wanted): bool
    {
        if ($joined === $wanted) {
            return true;
        }

        foreach (self::CURRENCY_TERMINATORS as $terminator) {
            if ($joined === $wanted.$terminator) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $words
     * @return array{left: int, top: int, right: int, bottom: int}
     */
    private function box(array $words): array
    {
        // Accumulated rather than collected: the run is never empty here, but a
        // min() over a list the compiler cannot prove non-empty is a crash
        // waiting for the one case that is.
        $left = null;
        $top = null;
        $right = 0;
        $bottom = 0;

        foreach ($words as $word) {
            $wordLeft = (int) ($word['l'] ?? 0);
            $wordTop = (int) ($word['y'] ?? 0);

            $left = $left === null ? $wordLeft : min($left, $wordLeft);
            $top = $top === null ? $wordTop : min($top, $wordTop);
            $right = max($right, $wordLeft + (int) ($word['w'] ?? 0));
            $bottom = max($bottom, $wordTop + (int) ($word['h'] ?? 0));
        }

        return [
            'left' => $left ?? 0,
            'top' => $top ?? 0,
            'right' => $right,
            'bottom' => $bottom,
        ];
    }

    /**
     * Strip what the recognizer may differ on but a reader would not.
     *
     * Spaces only. Punctuation is left alone because a thousands separator in
     * the wrong place is exactly the kind of misreading being judged, and
     * normalising it away would hide the error the reviewer is looking for.
     */
    private function normalise(string $text): string
    {
        return (string) preg_replace('/\s+/u', '', $text);
    }
}
