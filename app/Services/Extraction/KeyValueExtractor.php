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

        // The extractor emits words in block order, not in reading order across
        // a line, so a word further right can appear earlier in the list than
        // the value that sits immediately beside the label. Reading in list
        // order then measures the gap to whichever word happened to come first
        // and abandons a value that was never far away. Left-to-right is the
        // order the page is actually read in.
        uasort($candidates, fn (RecognizedWord $a, RecognizedWord $b): int => $a->left <=> $b->left);

        $labelIndices = $this->labelRunIndices($words);
        $value = $this->readValue($candidates, $anchor, $labelIndices);

        // A label too long for its column wraps, and the value is set against
        // the line the label *starts* on rather than the line its colon ends
        // up on. "Procuring Entity District :" occupies two lines with the
        // district name beside the first, leaving the matched line holding
        // nothing but the colon.
        if ($value === null) {
            $value = $this->readWrappedLabelValue($anchor, $words, $labelIndices);
        }

        if ($value === null) {
            return null;
        }

        $value = $this->appendWrappedValue($value, $words, $labelIndices);

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
     * Follow a value that wrapped onto further lines of its own column.
     *
     * Values wrap for the same reason labels do, and truncating one is worse
     * than failing to read it: "Education Engineering Department" cut to
     * "Education Engineering" still looks like an answer.
     *
     * Where a wrapped value ends is the whole difficulty, because the next
     * field's value begins in the same column at a similar distance. Measured
     * on a real notice, the lines inside one value sit 12 and 13px apart while
     * the step to the following value is 18px. Absolute spacing cannot separate
     * those; the change in spacing can, which is the same reasoning that
     * separates a label from a value on a crowded line.
     *
     * @param  array{value: string, confidences: list<float>, firstLeft: int, first: RecognizedWord}  $value
     * @param  list<RecognizedWord>  $words
     * @param  array<int, true>  $labelIndices
     * @return array{value: string, confidences: list<float>, firstLeft: int, first: RecognizedWord}
     */
    private function appendWrappedValue(array $value, array $words, array $labelIndices): array
    {
        $first = $value['first'];
        $height = max(1, $first->height);
        $tolerance = (float) config('civiclens.extraction.kv_wrap_step_tolerance', 1.4);
        $maxStep = max(1, (int) round($height * (float) config('civiclens.extraction.kv_wrap_line_multiple', 1.6)));
        $line = $first;
        $steps = [];

        while (true) {
            $next = null;
            $nextIndex = null;

            foreach ($words as $index => $word) {
                // The continuation starts in the same column, on a line below
                // the one just read, and is not part of a label.
                if (isset($labelIndices[$index]) || $word->top <= $line->top) {
                    continue;
                }

                // A wrapped line sits one line-height below its predecessor.
                // The step to the next field is several times that: measured
                // here, 12px within a value against 30px to the field below.
                // Without this bound the first step has nothing to compare
                // against and swallows whatever comes next.
                if (abs($word->left - $first->left) > $height || $word->top - $line->top > $maxStep) {
                    continue;
                }

                if ($next === null || $word->top < $next->top) {
                    $next = $word;
                    $nextIndex = $index;
                }
            }

            if ($next === null || $nextIndex === null) {
                return $value;
            }

            $step = (float) ($next->top - $line->top);

            // A step markedly wider than the ones already taken is the gap to
            // the next field, not the next line of this one.
            if ($steps !== [] && $step > $this->median($steps) * $tolerance) {
                return $value;
            }

            $continuation = $this->readValue(
                $this->lineFrom($next, $words, $nextIndex),
                $next,
                $labelIndices,
            );

            if ($continuation === null) {
                return $value;
            }

            $value['value'] = trim($value['value'].' '.$continuation['value']);
            $value['confidences'] = [...$value['confidences'], ...$continuation['confidences']];
            $steps[] = $step;
            $line = $next;
        }
    }

    /**
     * The candidate run beginning at a word and reading rightwards on its line.
     *
     * @param  list<RecognizedWord>  $words
     * @return array<int, RecognizedWord>
     */
    private function lineFrom(RecognizedWord $start, array $words, int $startIndex): array
    {
        $candidates = [$startIndex => $start];

        foreach ($words as $index => $word) {
            if ($index !== $startIndex && $start->sharesLineWith($word) && $word->left >= $start->left) {
                $candidates[$index] = $word;
            }
        }

        uasort($candidates, fn (RecognizedWord $a, RecognizedWord $b): int => $a->left <=> $b->left);

        return $candidates;
    }

    /**
     * The run of words forming a label, in reading order, starting at a word.
     *
     * Assembled from geometry rather than from list position. The extractor
     * emits words in block order, so the word printed beside a label can sit
     * anywhere in the list, and a key built by walking the list stops at
     * whichever word happened to be emitted next — which is how "Procurement
     * Nature", set over two lines with its value between them in the list, went
     * unmatched entirely.
     *
     * A label runs at word spacing across its line and then, if it is too long
     * for its column, continues on the next line at the same left edge.
     *
     * @param  list<RecognizedWord>  $words
     * @return array<int, RecognizedWord>
     */
    private function labelBlock(array $words, int $start): array
    {
        $first = $words[$start];
        $wordGap = max(1, (int) round($first->height * (float) config('civiclens.extraction.kv_value_gap_multiple', 1.5)));
        $block = [$start => $first];
        $tail = $first;

        while (true) {
            $next = null;
            $nextIndex = null;

            foreach ($words as $index => $word) {
                if (isset($block[$index])) {
                    continue;
                }

                $sameLine = $tail->sharesLineWith($word)
                    && $word->left >= $tail->right()
                    && $word->left - $tail->right() <= $wordGap;

                if (! $sameLine && ! $this->continues($tail, $word, $first)) {
                    continue;
                }

                // Nearest first, so the run follows the page rather than the
                // list: the next word on the line, else the start of the
                // continuation line.
                if ($next === null || ($sameLine ? $word->left < $next->left : $word->top < $next->top)) {
                    $next = $word;
                    $nextIndex = $index;
                }
            }

            if ($next === null || $nextIndex === null) {
                return $block;
            }

            $block[$nextIndex] = $next;
            $tail = $next;
        }
    }

    /**
     * Whether a word continues a label onto the next line of the same column.
     *
     * Both conditions are needed. Same column alone would join a label to an
     * unrelated field further down the page; next line alone would join it to
     * whatever sits in the value column.
     */
    private function continues(RecognizedWord $line, RecognizedWord $word, RecognizedWord $first): bool
    {
        $height = max(1, $line->height);

        return $word->top > $line->top
            && $word->top - $line->bottom() <= $height
            && abs($word->left - $first->left) <= $height;
    }

    /**
     * Read the value belonging to a label that wrapped onto a second line.
     *
     * The continuation line is only accepted when it begins in the same column
     * as the matched label, within a label height. That is what distinguishes a
     * wrapped label from an unrelated field that happens to sit above, and it
     * is why this cannot simply read the nearest line up.
     *
     * @param  list<RecognizedWord>  $words
     * @param  array<int, true>  $labelIndices
     * @return array{value: string, confidences: list<float>, firstLeft: int, first: RecognizedWord}|null
     */
    private function readWrappedLabelValue(RecognizedWord $anchor, array $words, array $labelIndices): ?array
    {
        $start = null;

        foreach ($words as $word) {
            if ($word->top >= $anchor->top || abs($word->left - $anchor->left) > max(1, $anchor->height)) {
                continue;
            }

            if ($start === null || $word->top > $start->top) {
                $start = $word;
            }
        }

        if ($start === null) {
            return null;
        }

        // Walk the label across its own line at word spacing. Where that run
        // ends is where the value column begins.
        $wordGap = max(1, (int) round($start->height * (float) config('civiclens.extraction.kv_value_gap_multiple', 1.5)));
        $tail = $start;
        $candidates = [];

        foreach ($words as $index => $word) {
            if (! $start->sharesLineWith($word) || $word->left < $start->left) {
                continue;
            }

            $candidates[$index] = $word;
        }

        uasort($candidates, fn (RecognizedWord $a, RecognizedWord $b): int => $a->left <=> $b->left);

        foreach ($candidates as $index => $word) {
            if ($word->left - $tail->right() > $wordGap) {
                break;
            }

            $tail = $word;
            unset($candidates[$index]);
        }

        return $candidates === [] ? null : $this->readValue($candidates, $tail, $labelIndices);
    }

    /**
     * Indices of words that belong to a label rather than to a value.
     *
     * Some forms set two label/value pairs on one line with no gutter between
     * the end of the first value and the start of the second label. Measured on
     * a real notice: the value "Open Tendering Method" ends at x=277 and the
     * next label "Budget Type :" begins at x=289 — a 12px gap against a 10px
     * line height, which is ordinary word spacing. No distance threshold can
     * separate that from the space between two words of the same value, so the
     * label has to be recognised as a label.
     *
     * A label is a short run of words ending in a colon. The run is grown
     * backwards from the colon and stops where the spacing changes character:
     * inside "Budget Type :" the words sit 2 and 3px apart, while the step back
     * to the preceding value word is 12px. Relative spacing separates them
     * where absolute spacing cannot.
     *
     * @param  list<RecognizedWord>  $words
     * @return array<int, true>
     */
    private function labelRunIndices(array $words): array
    {
        $marked = [];

        foreach ($words as $index => $word) {
            // "10:30" is a value. Only a word that ends in a colon terminates a
            // label.
            if (! str_ends_with($word->text, ':')) {
                continue;
            }

            $marked[$index] = true;
            $gaps = [];
            $leftmost = $word;

            // Walking backwards through the list would assume list order
            // matches left-to-right order on the line, which it does not. The
            // neighbours are taken by position instead.
            foreach ($this->leftNeighbours($words, $index) as $previous => $candidate) {
                if ($candidate->right() > $leftmost->left) {
                    continue;
                }

                $gap = $leftmost->left - $candidate->right();

                // The first step back has nothing to compare against, so it is
                // admitted on the absolute allowance alone.
                if ($gaps !== [] && $gap > max(1.0, $this->median($gaps) * 3)) {
                    break;
                }

                $marked[$previous] = true;
                $gaps[] = (float) $gap;
                $leftmost = $candidate;
            }
        }

        return $marked;
    }

    /**
     * Words sharing a line with the given one and sitting to its left, nearest
     * first.
     *
     * @param  list<RecognizedWord>  $words
     * @return array<int, RecognizedWord>
     */
    private function leftNeighbours(array $words, int $index): array
    {
        $subject = $words[$index];
        $neighbours = [];

        foreach ($words as $other => $word) {
            if ($other !== $index && $subject->sharesLineWith($word) && $word->left < $subject->left) {
                $neighbours[$other] = $word;
            }
        }

        uasort($neighbours, fn (RecognizedWord $a, RecognizedWord $b): int => $b->left <=> $a->left);

        return $neighbours;
    }

    /** @param list<float> $values */
    private function median(array $values): float
    {
        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];
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
     * @param  array<int, true>  $labelIndices
     * @return array{value: string, confidences: list<float>, firstLeft: int, first: RecognizedWord}|null
     */
    private function readValue(array $candidates, RecognizedWord $anchor, array $labelIndices): ?array
    {
        $first = null;
        $columnGap = max(1, (int) round($anchor->height * (float) config('civiclens.extraction.kv_gap_multiple', 8)));
        $wordGap = max(1, (int) round($anchor->height * (float) config('civiclens.extraction.kv_value_gap_multiple', 1.5)));
        $previousRight = $anchor->right();
        $text = [];
        $confidences = [];

        foreach ($candidates as $index => $word) {
            // Once the value has started, a word belonging to a label belongs
            // to the *next* field. Before it has started, the words still
            // marked are the anchor's own terminator.
            if ($text !== [] && isset($labelIndices[$index])) {
                break;
            }

            // Anything still part of the label before the value begins is the
            // label's own tail: its colon, and words like the "No." in
            // "Invitation Reference No. :". Treating the colon as the value is
            // what made a two-column form report ":" for every field — the read
            // started at the colon, and the gutter to the real value then
            // measured as an over-wide gap from there.
            if ($text === [] && (isset($labelIndices[$index]) || ! $this->carriesContent($word->text))) {
                $previousRight = $word->right();

                continue;
            }

            $limit = $text === [] ? $columnGap : $wordGap;

            if ($word->left - $previousRight > $limit) {
                break;
            }

            $first ??= $word;
            $text[] = $word->text;
            $confidences[] = $word->confidence;
            $previousRight = $word->right();
        }

        $value = trim(implode(' ', $text));

        if ($value === '' || $confidences === [] || $first === null) {
            return null;
        }

        return ['value' => $value, 'confidences' => $confidences, 'firstLeft' => $first->left, 'first' => $first];
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

        foreach (array_keys($words) as $start) {
            $buffer = '';

            foreach ($this->labelBlock($words, $start) as $index => $word) {
                $buffer .= ($buffer === '' ? '' : ' ').$word->text;

                if (str_contains($this->matcher->normalize($buffer), $key)) {
                    return ['start' => $start, 'end' => $index];
                }
            }
        }

        return null;
    }
}
