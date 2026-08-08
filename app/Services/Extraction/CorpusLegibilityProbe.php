<?php

namespace App\Services\Extraction;

use App\Contracts\Extraction\OcrEngine;
use App\Data\Extraction\RecognizedWord;

/**
 * Measure whether an engine can see a corpus at all, before anything is built on it.
 *
 * Extraction accuracy conflates two very different failures: the engine could
 * not read the text, or it read the text and the extraction logic put it in the
 * wrong place. Only the second is worth engineering against. This separates
 * them by asking, per field, whether the gold label and the gold value appear
 * anywhere in the recognized text — ignoring position entirely.
 *
 * The value rate is a ceiling. No extractor, however good its layout model, can
 * return a value the engine never recognized.
 */
class CorpusLegibilityProbe
{
    public function __construct(
        private readonly BenchmarkManifestReader $reader,
        private readonly OcrEngine $engine,
        private readonly FieldValueMatcher $matcher,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function probe(string $manifestPath, ?int $limitPages = null): array
    {
        $pages = $this->reader->read($manifestPath);

        if ($limitPages !== null && $limitPages > 0) {
            $pages = array_slice($pages, 0, $limitPages);
        }

        $fields = 0;
        $labelsFound = 0;
        $valuesFound = 0;
        $byScript = [];

        foreach ($pages as $page) {
            $result = $this->engine->recognize(
                $page->imagePath,
                (int) config('civiclens.extraction.ocr.primary_dpi', 150),
                (int) config('civiclens.extraction.ocr.primary_psm', 3),
            );
            $text = implode(' ', array_map(fn (RecognizedWord $word): string => $word->text, $result->words));

            foreach ($page->goldFields as $label => $value) {
                $fields++;
                $label_ok = $this->matcher->matches($label, $text);
                $value_ok = $this->matcher->matches($value, $text);
                $labelsFound += $label_ok ? 1 : 0;
                $valuesFound += $value_ok ? 1 : 0;

                $bucket = &$byScript[$page->scriptClass];
                $bucket['fields'] = ($bucket['fields'] ?? 0) + 1;
                $bucket['labels'] = ($bucket['labels'] ?? 0) + ($label_ok ? 1 : 0);
                $bucket['values'] = ($bucket['values'] ?? 0) + ($value_ok ? 1 : 0);
                unset($bucket);
            }
        }

        return [
            'pages' => count($pages),
            'fields' => $fields,
            'label_recognition_rate' => $this->rate($labelsFound, $fields),
            'value_recognition_rate' => $this->rate($valuesFound, $fields),
            'extraction_ceiling' => $this->rate($valuesFound, $fields),
            'by_script' => array_map(fn (array $b): array => [
                'fields' => $b['fields'],
                'label_recognition_rate' => $this->rate($b['labels'], $b['fields']),
                'value_recognition_rate' => $this->rate($b['values'], $b['fields']),
            ], $byScript),
            'verdict' => $this->verdict($this->rate($valuesFound, $fields)),
        ];
    }

    private function rate(int $count, int $total): ?float
    {
        return $total > 0 ? round($count / $total, 4) : null;
    }

    /**
     * A corpus whose values the engine cannot read is not an extraction problem.
     * Saying so plainly stops effort going into layout logic that cannot help.
     */
    private function verdict(?float $ceiling): string
    {
        return match (true) {
            $ceiling === null => 'No fields probed.',
            $ceiling < 0.25 => 'Intractable for this engine: the values are mostly unrecognized, so extraction logic cannot raise accuracy. Change the recognizer or the corpus, not the extractor.',
            $ceiling < 0.75 => 'Partially legible: extraction logic can only compete for the recognized share.',
            default => 'Legible: remaining error is attributable to extraction logic rather than recognition.',
        };
    }
}
