<?php

namespace App\Services\Extraction;

use App\Contracts\Extraction\OcrEngine;
use App\Data\Extraction\BenchmarkPage;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a gold-annotated benchmark into real calibration data.
 *
 * Until this exists, every calibration number is synthetic: the conformal
 * machinery can be validated but no empirical claim can be made. This runs the
 * production OCR path over benchmark pages and records one extraction_field per
 * gold field with a genuine prediction, outcome, and nonconformity score.
 */
class BenchmarkEvaluationService
{
    public function __construct(
        private readonly BenchmarkManifestReader $reader,
        private readonly OcrEngine $engine,
        private readonly FieldValueMatcher $matcher,
        private readonly KeyValueExtractor $extractor,
        private readonly ScriptClassifier $scripts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(string $manifestPath, string $benchmark = 'benchmark'): array
    {
        $pages = $this->reader->read($manifestPath);

        $run = ExtractionRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'benchmark' => $benchmark,
            'status' => 'running',
            'routing_decision' => 'ocr_required',
            'engine' => 'tesseract',
            'engine_version' => 'pending',
            'config_hash' => hash('sha256', $benchmark.'|'.($this->languages())),
            'language_hint' => $this->languages(),
            'started_at' => now(),
        ]);

        $fields = 0;
        $correct = 0;
        $engineVersion = 'unknown';

        foreach ($pages as $index => $page) {
            $result = $this->engine->recognize(
                $page->imagePath,
                (int) config('civiclens.extraction.ocr.primary_dpi', 150),
                (int) config('civiclens.extraction.ocr.primary_psm', 3),
            );
            $engineVersion = $result->engineVersion;

            $extractionPage = $run->pages()->create([
                'page_number' => $index + 1,
                'script_class' => $page->scriptClass,
                'extraction_path' => SelectiveOcrService::PRIMARY,
                'confidence' => $result->meanConfidence,
                'extracted_text' => $result->text,
                'content_hash' => hash('sha256', $result->text),
                'character_count' => mb_strlen($result->text),
                'word_count' => $result->wordCount,
                'duration_ms' => $result->durationMs,
            ]);

            foreach ($page->goldFields as $key => $goldValue) {
                // The field key is the printed label. Predict from the label's
                // position, never by searching for the gold value, so the score
                // stays independent of the outcome it is meant to rank.
                $prediction = $this->extractor->extract($key, $result->words);
                $isCorrect = $prediction !== null && $this->matcher->matches($goldValue, $prediction['value']);

                $this->recordField($run, $extractionPage, $page, $key, $goldValue, $prediction, $isCorrect);
                $fields++;

                if ($isCorrect) {
                    $correct++;
                }
            }
        }

        DB::transaction(function () use ($run, $pages, $engineVersion): void {
            $run->forceFill([
                'status' => 'completed',
                'engine_version' => $engineVersion,
                'page_count' => count($pages),
                'pages_ocr_primary' => count($pages),
                'peak_memory_bytes' => memory_get_peak_usage(true),
                'completed_at' => now(),
            ])->save();
        });

        return [
            'benchmark' => $benchmark,
            'run_uuid' => $run->uuid,
            'pages' => count($pages),
            'fields' => $fields,
            'correct' => $correct,
            'field_accuracy' => $fields > 0 ? round($correct / $fields, 4) : null,
        ];
    }

    /** @param array{value: string, confidences: list<float>}|null $prediction */
    private function recordField(
        ExtractionRun $run,
        ExtractionPage $page,
        BenchmarkPage $benchmarkPage,
        string $key,
        string $goldValue,
        ?array $prediction,
        bool $correct,
    ): void {
        $confidence = $this->spanConfidence($prediction['confidences'] ?? null);

        ExtractionField::query()->create([
            'extraction_run_id' => $run->getKey(),
            'extraction_page_id' => $page->getKey(),
            'field_key' => $key,
            'field_type' => 'string',
            'extracted_value' => $prediction['value'] ?? null,
            'normalized_value' => $prediction === null ? null : $this->matcher->normalize($prediction['value']),
            // The manifest declares the script class of the page; the classifier
            // sees only what the engine produced. Trust the annotation, since a
            // group label derived from a bad read would corrupt the partition.
            'script_class' => $benchmarkPage->scriptClass !== 'unknown'
                ? $benchmarkPage->scriptClass
                : $this->scripts->classify($prediction['value'] ?? ''),
            'publisher_group' => $benchmarkPage->publisherGroup,
            'calibration_split' => $benchmarkPage->split,
            'confidence' => $confidence,
            'nonconformity_score' => $this->nonconformity($confidence),
            'decision' => 'pending',
            'evidence_page_number' => $benchmarkPage->pageNumber,
            'gold_value' => $goldValue,
            'gold_source' => 'benchmark',
            'is_correct' => $correct,
        ]);
    }

    /**
     * The weakest word in the supporting span, not its average.
     *
     * A field is only as trustworthy as its least legible word: one garbled
     * digit in an amount makes the whole value wrong, and averaging would hide
     * that behind the surrounding words.
     *
     * @param  list<float>|null  $span
     */
    private function spanConfidence(?array $span): ?float
    {
        if ($span === null || $span === []) {
            return null;
        }

        return round(min($span), 4);
    }

    /**
     * Lower is more conforming. A field with no supporting span has no
     * confidence, which is maximal nonconformity rather than zero.
     */
    private function nonconformity(?float $confidence): float
    {
        if ($confidence === null) {
            return 1.0;
        }

        return round(max(0.0, min(1.0, 1.0 - ($confidence / 100))), 8);
    }

    private function languages(): string
    {
        return (string) config('civiclens.extraction.ocr.languages', 'ben+eng');
    }
}
