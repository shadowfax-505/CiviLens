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

    /**
     * Read, kept, and not certified.
     *
     * A page whose mean word confidence never reached the threshold used to
     * store nothing at all, so recovering it meant recognising the whole thing
     * again. Its text is now kept and plainly marked: searchable, available to a
     * better recognizer later, and excluded from candidates so none of it enters
     * the guarantee. Blank pages still abstain outright — there is nothing to
     * keep.
     */
    public const LOW_CONFIDENCE = 'ocr_low_confidence';

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

        // Some formats have no page image to fall back on.
        //
        // A thin text layer means "send it to OCR", and OCR means rasterise the
        // page — which pdftoppm can only do for a PDF. An HTML page with little
        // text was therefore handed to a rasterizer that cannot read it, the
        // exception failed the whole run, and every other page in that run was
        // lost with it. 200 runs sat failed that way, 99 of them the statute
        // pages of the government's legal database.
        //
        // There is nothing to read and that is a result, not a breakage: the
        // page abstains, with the reason recorded, and the run completes.
        if ($artifact instanceof SourceArtifactVersion && ! $this->rasterizable($artifact)) {
            foreach ($pending as $page) {
                $page->forceFill([
                    'extraction_path' => self::ABSTAINED,
                    'failure_reason' => 'No page image exists for this format, and the text layer was too thin to use.',
                ])->save();
            }

            return $this->recount($run);
        }

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

    /**
     * Read one page again, whatever it was routed to last time.
     *
     * `process()` only visits pages still marked as needing OCR, which is right
     * for a first pass and useless for a second: an abstained page is not
     * pending, it is finished and empty. This is the way back for the 1,386
     * pages that were read before the recognizer learned to straighten a
     * sideways page and before a value could be judged on its own confidence.
     *
     * Idempotent. Re-reading a page that now succeeds simply stores the better
     * result, and re-reading one that still fails leaves it where it was.
     */
    public function reread(ExtractionPage $page): void
    {
        $run = $page->run;
        $artifact = $run?->artifactVersion;

        if (! $artifact instanceof SourceArtifactVersion) {
            throw new ExtractionFailed('Page is not linked to a source artifact.');
        }

        $artifactPath = $this->workspace->materialize($artifact);

        try {
            $this->processPage($page, $artifactPath);
        } finally {
            $this->workspace->discard($artifactPath);
        }
    }

    /**
     * Whether this format has page images at all.
     *
     * Word documents count: `ArtifactWorkspace` renders them to PDF on the way
     * out, so by the time a rasterizer sees one it is a PDF (ADR-022).
     */
    private function rasterizable(SourceArtifactVersion $artifact): bool
    {
        return strtolower((string) $artifact->media_type) === 'application/pdf'
            || in_array(strtolower((string) $artifact->media_type), WordDocumentConverter::MEDIA_TYPES, true);
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

        // The enhanced pass also straightens the page.
        //
        // A landscape form or budget table printed on a portrait page reaches
        // OCR sideways and comes back as gibberish. Six pages in a
        // twenty-four-page sample of readable-but-abstained pages needed a
        // quarter turn; rotating and re-reading lifted mean confidence by
        // twenty to twenty-seven points and roughly doubled the recovered text.
        //
        // Only on the second pass, so a page the primary read fine never pays
        // for orientation detection.
        $enhanced = $this->attempt(
            $artifactPath,
            $page->page_number,
            (int) config('civiclens.extraction.ocr.enhanced_dpi', 300),
            (int) config('civiclens.extraction.ocr.enhanced_psm', 6),
            straighten: true,
            rotationApplied: $rotation,
        );

        $elapsed = $primary->durationMs + $enhanced->durationMs;

        if ($enhanced->isConfident($threshold)) {
            $this->store($page, $enhanced, self::ENHANCED, $elapsed, $rotation);

            return;
        }

        $best = $this->betterOf($primary, $enhanced);

        // Nothing on the page worth keeping. Ink coverage and the abstention
        // classifier already tell a blank page from a badly printed one, and a
        // blank page's text is an empty string however hard it is read.
        if (trim($best->text) === '') {
            $page->forceFill([
                'extraction_path' => self::ABSTAINED,
                'confidence' => $best->meanConfidence,
                'script_class' => $this->scripts->classify($best->text),
                'extracted_text' => null,
                'content_hash' => null,
                'character_count' => 0,
                'word_count' => 0,
                'duration_ms' => $elapsed,
                'failure_reason' => 'Nothing legible on the page after one enhanced pass.',
            ])->save();

            return;
        }

        // Read but not vouched for. The mean is a page-level statistic and the
        // guarantee is per field: on one page measured here the mean was 74.1
        // while its 56 numeric tokens averaged 83.5, so discarding the page
        // threw away fifty-six figures that were read well. The text is kept
        // and marked; whether any individual value is good enough to offer a
        // reviewer is decided per token, where that question belongs.
        $this->store(
            $page,
            $best,
            self::LOW_CONFIDENCE,
            $elapsed,
            $best === $enhanced ? $rotation : null,
            'Confidence remained below the acceptance threshold after one enhanced pass.',
        );
    }

    private function attempt(
        string $artifactPath,
        int $pageNumber,
        int $dpi,
        int $psm,
        bool $straighten = false,
        ?int &$rotationApplied = null,
    ): OcrPageResult {
        $imagePath = $this->rasterizer->rasterize($artifactPath, $pageNumber, $dpi);
        $straightened = null;

        try {
            if ($straighten) {
                $degrees = $this->engine->detectRotation($imagePath);
                $straightened = $degrees === null ? null : $this->rotate($imagePath, $degrees);

                if ($straightened !== null) {
                    $rotationApplied = $degrees;
                }
            }

            return $this->engine->recognize($straightened ?? $imagePath, $dpi, $psm);
        } finally {
            if ($straightened !== null) {
                @unlink($straightened);
            }

            $this->rasterizer->discard($imagePath);
        }
    }

    /**
     * A turned copy of the page image, or null if it could not be made.
     *
     * GD rather than ImageMagick: `imagerotate` is already a dependency — the
     * second read works on `GdImage` — and this machine has no `magick`
     * binary. A rotation that fails returns null and the page is read as
     * stored, which is what happened before any of this existed.
     *
     * GD rotates anticlockwise and Tesseract reports the clockwise turn needed,
     * hence the negation. Getting this backwards would turn a sideways page
     * further sideways, and it would look like the rotation had simply not
     * helped.
     */
    private function rotate(string $imagePath, int $degrees): ?string
    {
        $source = @imagecreatefrompng($imagePath);

        if (! $source instanceof \GdImage) {
            return null;
        }

        // imagedestroy is deprecated from PHP 8.5 and has done nothing since
        // 8.0: a GdImage is an object and is collected like one.
        $rotated = @imagerotate($source, -$degrees, 0);

        if (! $rotated instanceof \GdImage) {
            return null;
        }

        $destination = $imagePath.'.straightened.png';
        $written = @imagepng($rotated, $destination);

        return $written ? $destination : null;
    }

    private function betterOf(OcrPageResult $first, OcrPageResult $second): OcrPageResult
    {
        return ($second->meanConfidence ?? -1.0) > ($first->meanConfidence ?? -1.0) ? $second : $first;
    }

    private function store(
        ExtractionPage $page,
        OcrPageResult $result,
        string $path,
        int $elapsed,
        ?int $rotation = null,
        ?string $reason = null,
    ): void {
        $page->forceFill([
            'extraction_path' => $path,
            // How far the page was turned before it was read. Word geometry is
            // recorded in the rotated frame, so anything drawing a crop on the
            // page image later has to turn it the same way first — a box drawn
            // in the wrong frame is the shape of bug that produced a one-pixel
            // crop earlier in this project.
            'rotation_applied' => $rotation,
            'failure_reason' => $reason,
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
