<?php

namespace App\Services\Extraction;

use App\Data\Extraction\FieldExtractionSignals;
use App\Data\Extraction\RecognizedWord;

/**
 * Propose a value for a labelled form field from page geometry alone.
 *
 * The gold value is never consulted. That independence is the whole point: an
 * extractor that searches for the answer inside the recognized text produces a
 * score which is a function of the label, and any risk guarantee computed from
 * it is vacuous. This proposes a candidate the same way a reader would — find
 * the printed label, then read what sits beside it — so the prediction can be
 * right or wrong on its own terms.
 */
class KeyValueExtractor
{
    public function __construct(private readonly FieldValueMatcher $matcher) {}

    /**
     * @param  list<RecognizedWord>  $words
     * @return array{value: string, confidences: list<float>, signals: FieldExtractionSignals}|null
     */
    public function extract(string $keyText, array $words): ?array
    {
        $keySpan = $this->locateKey($keyText, $words);

        if ($keySpan === null) {
            return null;
        }

        $anchor = $words[$keySpan['end']];
        $candidates = [];

        foreach ($words as $index => $word) {
            if ($index <= $keySpan['end'] || ! $anchor->sharesLineWith($word) || $word->left < $anchor->right()) {
                continue;
            }

            $candidates[$index] = $word;
        }

        if ($candidates === []) {
            return null;
        }

        $value = $this->readValue($candidates, $anchor);

        if ($value === null) {
            return null;
        }

        $firstIndex = (int) array_key_first($candidates);

        return $value + ['signals' => new FieldExtractionSignals(
            true,
            $keySpan['end'] - $keySpan['start'] + 1,
            $anchor->height > 0 ? ($words[$firstIndex]->left - $anchor->right()) / $anchor->height : 0.0,
            count($value['confidences']),
            preg_match('/^[\w\-\/.,() ]+$/u', $value['value']) === 1,
            $this->competingLabelsOnLine($anchor, $words, $keySpan['end']),
        )];
    }

    /**
     * How many other labels share this line.
     *
     * A crowded line makes it likelier that the value read belongs to a
     * neighbouring field, so the count is structural evidence about the read
     * rather than about the answer.
     *
     * @param  list<RecognizedWord>  $words
     */
    private function competingLabelsOnLine(RecognizedWord $anchor, array $words, int $anchorIndex): int
    {
        $competing = 0;

        foreach ($words as $index => $word) {
            if ($index === $anchorIndex || ! $anchor->sharesLineWith($word)) {
                continue;
            }

            if (str_contains($word->text, ':')) {
                $competing++;
            }
        }

        return $competing;
    }

    /**
     * Stop at the first wide horizontal gap.
     *
     * Forms place several label/value pairs on one line, so reading to the end
     * of the line would swallow the next field's label. A gap much wider than
     * the anchor's own height is the boundary between columns.
     *
     * The multiple is configuration because it is an operating point that
     * differs by publisher: a form laid out as a table puts its value far to the
     * right of the label, while dense prose puts it immediately after.
     *
     * @param  array<int, RecognizedWord>  $candidates
     * @return array{value: string, confidences: list<float>}|null
     */
    private function readValue(array $candidates, RecognizedWord $anchor): ?array
    {
        $gapLimit = max(1, (int) round($anchor->height * (float) config('civiclens.extraction.kv_gap_multiple', 3)));
        $previousRight = $anchor->right();
        $text = [];
        $confidences = [];

        foreach ($candidates as $word) {
            if ($word->left - $previousRight > $gapLimit && $text !== []) {
                break;
            }

            $text[] = $word->text;
            $confidences[] = $word->confidence;
            $previousRight = $word->right();
        }

        $value = trim(implode(' ', $text));

        if ($value === '' || $confidences === []) {
            return null;
        }

        return ['value' => $value, 'confidences' => $confidences];
    }

    /**
     * @param  list<RecognizedWord>  $words
     * @return array{start: int, end: int}|null
     */
    private function locateKey(string $keyText, array $words): ?array
    {
        $key = $this->matcher->normalize($keyText);

        if ($key === '' || $words === []) {
            return null;
        }

        $count = count($words);

        for ($start = 0; $start < $count; $start++) {
            $buffer = '';

            for ($end = $start; $end < $count; $end++) {
                if ($end > $start && ! $words[$start]->sharesLineWith($words[$end])) {
                    break;
                }

                $buffer .= ($end === $start ? '' : ' ').$words[$end]->text;

                if (str_contains($this->matcher->normalize($buffer), $key)) {
                    return ['start' => $start, 'end' => $end];
                }
            }
        }

        return null;
    }
}
