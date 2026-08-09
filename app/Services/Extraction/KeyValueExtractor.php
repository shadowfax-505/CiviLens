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

        return [
            'value' => $value['value'],
            'confidences' => $value['confidences'],
            'signals' => new FieldExtractionSignals(
                true,
                $keySpan['end'] - $keySpan['start'] + 1,
                // Measured to the first word of the value, not to the first
                // candidate. The first candidate is usually the label's own
                // colon sitting flush against it, which reports a gap of
                // roughly zero for every field and so ranks nothing.
                $anchor->height > 0 ? ($value['firstLeft'] - $anchor->right()) / $anchor->height : 0.0,
                count($value['confidences']),
                preg_match('/^[\w\-\/.,() ]+$/u', $value['value']) === 1,
                $this->competingLabelsOnLine($anchor, $words, $keySpan['end']),
            ),
        ];
    }

    /**
     * Whether a token carries anything but punctuation and spacing.
     */
    private function carriesContent(string $text): bool
    {
        return preg_match('/[\p{L}\p{N}]/u', $text) === 1;
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
     * Read the value beside the label, crossing one column gap and no more.
     *
     * Two distances matter and they are not the same distance. Getting from the
     * label to its value crosses the gutter between a form's label column and
     * its value column, which is wide. Getting from one word of the value to the
     * next crosses a word space, which is narrow. Measured on real e-GP tender
     * notices: the label-to-value gutter is 67px against a 11px label height,
     * while words inside a value sit 3px apart.
     *
     * Allowing the wide gap everywhere is what swallows the next field. On those
     * same notices the following label sits 55px past the end of the value —
     * closer than the value was to its own label. A single threshold cannot
     * separate those two cases, so the jump into the value column is permitted
     * once and the rest of the value is read at word spacing.
     *
     * Both multiples are configuration because they are operating points that
     * differ by publisher: a tabular layout puts its value far to the right,
     * while dense prose puts it immediately after.
     *
     * @param  array<int, RecognizedWord>  $candidates
     * @return array{value: string, confidences: list<float>, firstLeft: int}|null
     */
    private function readValue(array $candidates, RecognizedWord $anchor): ?array
    {
        $firstLeft = null;
        $columnGap = max(1, (int) round($anchor->height * (float) config('civiclens.extraction.kv_gap_multiple', 8)));
        $wordGap = max(1, (int) round($anchor->height * (float) config('civiclens.extraction.kv_value_gap_multiple', 1.5)));
        $previousRight = $anchor->right();
        $text = [];
        $confidences = [];

        foreach ($candidates as $word) {
            // Punctuation before the first word of the value is the label's own
            // terminator, not content. Treating the colon as the value is what
            // made a two-column form report ":" for every field: the read
            // started at the colon, and the gutter to the real value then
            // measured as an over-wide gap from there.
            if ($text === [] && ! $this->carriesContent($word->text)) {
                $previousRight = $word->right();

                continue;
            }

            $limit = $text === [] ? $columnGap : $wordGap;

            if ($word->left - $previousRight > $limit) {
                break;
            }

            $firstLeft ??= $word->left;
            $text[] = $word->text;
            $confidences[] = $word->confidence;
            $previousRight = $word->right();
        }

        $value = trim(implode(' ', $text));

        if ($value === '' || $confidences === [] || $firstLeft === null) {
            return null;
        }

        return ['value' => $value, 'confidences' => $confidences, 'firstLeft' => $firstLeft];
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
