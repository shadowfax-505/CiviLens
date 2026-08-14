<?php

namespace App\Services\Extraction;

use App\Contracts\Extraction\OcrEngine;
use App\Data\Extraction\OcrPageResult;
use App\Data\Extraction\RecognizedWord;
use App\Exceptions\Extraction\ExtractionFailed;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\SourceArtifactVersion;
use Illuminate\Support\Facades\DB;
use Throwable;

class SelectiveOcrService
{
    public const PRIMARY = 'ocr_primary';

    public const ENHANCED = 'ocr_enhanced';

    public const ABSTAINED = 'abstained';

    public function __construct(
        private readonly OcrEngine $engine,
        private readonly PageRasterizer $rasterizer,
        private readonly ScriptClassifier $scripts,
        private readonly ArtifactWorkspace $workspace,
    ) {}

    /**
     * Run OCR only on pages the native routing could not satisfy.
     *
     * Exactly one enhanced pass is permitted. A page that is still not confident
     * after it abstains to manual review rather than contributing text nobody
     * vouched for — an abstention is a recorded outcome, not a failure.
     */
    public function process(ExtractionRun $run): ExtractionRun
    {
        $pending = $run->pages()->where('extraction_path', PageRoutingPolicy::OCR_REQUIRED)->orderBy('page_number')->get();

        if ($pending->isEmpty()) {
            return $run;
        }

        $artifact = $run->artifactVersion;

        if (! $artifact instanceof SourceArtifactVersion) {
            throw new ExtractionFailed('Extraction run is not linked to a source artifact.');
        }

        $artifactPath = null;

        try {
            $artifactPath = $this->workspace->materialize($artifact);

            foreach ($pending as $page) {
                $this->processPage($page, $artifactPath);
            }
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'failure_reason' => str('Selective OCR failed.')->limit(500)->toString(),
                'completed_at' => now(),
            ])->save();

            throw $exception;
        } finally {
            $this->workspace->discard($artifactPath);
        }

        return $this->recount($run);
    }

    private function processPage(ExtractionPage $page, string $artifactPath): void
    {
        $threshold = (float) config('civiclens.extraction.ocr.accept_confidence', 80.0);

        $primary = $this->attempt(
            $artifactPath,
            $page->page_number,
            (int) config('civiclens.extraction.ocr.primary_dpi', 150),
            (int) config('civiclens.extraction.ocr.primary_psm', 3),
        );

        if ($primary->isConfident($threshold)) {
            $this->store($page, $primary, self::PRIMARY, $primary->durationMs);

            return;
        }

        $enhanced = $this->attempt(
            $artifactPath,
            $page->page_number,
            (int) config('civiclens.extraction.ocr.enhanced_dpi', 300),
            (int) config('civiclens.extraction.ocr.enhanced_psm', 6),
        );

        $elapsed = $primary->durationMs + $enhanced->durationMs;

        if ($enhanced->isConfident($threshold)) {
            $this->store($page, $enhanced, self::ENHANCED, $elapsed);

            return;
        }

        // Keep the better of the two confidences on the abstention so the
        // calibration record shows how close the page came, but store no text.
        $best = $this->betterOf($primary, $enhanced);

        $page->forceFill([
            'extraction_path' => self::ABSTAINED,
            'confidence' => $best->meanConfidence,
            'script_class' => $this->scripts->classify($best->text),
            'extracted_text' => null,
            'content_hash' => null,
            'character_count' => 0,
            'word_count' => 0,
            'duration_ms' => $elapsed,
            'failure_reason' => 'Confidence remained below the acceptance threshold after one enhanced pass.',
        ])->save();
    }

    private function attempt(string $artifactPath, int $pageNumber, int $dpi, int $psm): OcrPageResult
    {
        $imagePath = $this->rasterizer->rasterize($artifactPath, $pageNumber, $dpi);

        try {
            return $this->engine->recognize($imagePath, $dpi, $psm);
        } finally {
            $this->rasterizer->discard($imagePath);
        }
    }

    private function betterOf(OcrPageResult $first, OcrPageResult $second): OcrPageResult
    {
        return ($second->meanConfidence ?? -1.0) > ($first->meanConfidence ?? -1.0) ? $second : $first;
    }

    private function store(ExtractionPage $page, OcrPageResult $result, string $path, int $elapsed): void
    {
        $page->forceFill([
            'extraction_path' => $path,
            'confidence' => $result->meanConfidence,
            'script_class' => $this->scripts->classify($result->text),
            'extracted_text' => $result->text,
            // Kept so a value can later be traced to the table cell it sat in.
            // The recognizer reports these and nothing was storing them.
            'recognized_words' => array_map(
                fn (RecognizedWord $word): array => [
                    't' => $word->text,
                    'c' => $word->confidence,
                    'l' => $word->left,
                    'y' => $word->top,
                    'w' => $word->width,
                    'h' => $word->height,
                ],
                $result->words,
            ),
            // Pixels mean nothing without the scale they were measured at: the
            // enhanced pass reads at 300 and the primary at 150, and a cell box
            // rendered at the other one puts every word in the wrong column.
            'recognized_dpi' => $result->dpi,
            'content_hash' => hash('sha256', $result->text),
            'character_count' => mb_strlen($result->text),
            'word_count' => $result->wordCount,
            'duration_ms' => $elapsed,
            'failure_reason' => null,
        ])->save();
    }

    private function recount(ExtractionRun $run): ExtractionRun
    {
        DB::transaction(function () use ($run): void {
            $counts = $run->pages()
                ->selectRaw('extraction_path, count(*) as total')
                ->groupBy('extraction_path')
                ->pluck('total', 'extraction_path');

            $run->forceFill([
                'engine' => $run->engine.'+tesseract',
                'pages_ocr_primary' => (int) ($counts[self::PRIMARY] ?? 0),
                'pages_ocr_enhanced' => (int) ($counts[self::ENHANCED] ?? 0),
                'pages_abstained' => (int) ($counts[self::ABSTAINED] ?? 0),
                'routing_decision' => (int) ($counts[PageRoutingPolicy::NATIVE] ?? 0) === $run->pages()->count() ? PageRoutingPolicy::NATIVE : 'mixed',
                'completed_at' => now(),
            ])->save();
        });

        return $run->refresh();
    }
}
