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
    /** Words joined either side of the value, in case it was split in reading. */
    private const MAX_WORDS_PER_VALUE = 4;

    /**
     * @return list<array{left: int, top: int, right: int, bottom: int}>
     */
    public function locate(ExtractionField $field, ExtractionPage $page): array
    {
        $cell = $field->tableCell;

        // A cell-derived value knows its own box exactly, and no search over the
        // page can improve on that.
        if ($cell instanceof ExtractionTableCell) {
            return [[
                'left' => (int) $cell->box_left,
                'top' => (int) $cell->box_top,
                'right' => (int) $cell->box_right,
                'bottom' => (int) $cell->box_bottom,
            ]];
        }

        $words = $page->recognized_words;

        if (! is_array($words) || $words === []) {
            return [];
        }

        $wanted = $this->normalise((string) $field->extracted_value);

        if ($wanted === '') {
            return [];
        }

        return $this->search(array_values($words), $wanted);
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

                if ($joined === $wanted) {
                    $found[] = $this->box(array_slice($words, $start, $span + 1));

                    // Past the words just consumed: a run that matched cannot
                    // also start inside itself.
                    $start += $span;

                    break;
                }

                // Once the run is longer than the value it can only grow, so
                // there is nothing left to find from this starting word.
                if (mb_strlen($joined) >= mb_strlen($wanted)) {
                    break;
                }
            }
        }

        return $found;
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
