<?php

namespace App\Services\Extraction;

use App\Data\Extraction\RecognizedWord;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\SourceArtifactVersion;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * Give already-extracted pages the word boxes nobody was storing.
 *
 * Extraction discarded word geometry until it was persisted, so 1,238 of 1,284
 * pages hold text with no coordinates and cannot be assigned to table cells at
 * all. Re-reading them is the only way to recover it.
 *
 * It writes geometry and nothing else. Text, confidence and the extraction path
 * stay exactly as they were: a reviewer may already have judged a value against
 * this page, and quietly replacing the text underneath a label would invalidate
 * it without anyone noticing. Where the re-read disagrees with what is stored,
 * that is counted and reported rather than acted on.
 */
class WordGeometryBackfill
{
    /** Paths whose text came from a raster, which is what word boxes describe. */
    private const RASTER_PATHS = ['ocr_primary', 'ocr_enhanced'];

    public function __construct(
        private readonly ArtifactWorkspace $workspace,
        private readonly PageRasterizer $rasterizer,
        private readonly TesseractOcrEngine $engine,
    ) {}

    /**
     * @return array{considered: int, filled: int, skipped: int, text_differs: int}
     */
    public function backfill(int $limit, bool $candidatesOnly = true): array
    {
        $considered = 0;
        $filled = 0;
        $skipped = 0;
        $differs = 0;

        foreach ($this->pages($limit, $candidatesOnly) as $page) {
            $considered++;

            try {
                $words = $this->read($page);
            } catch (Throwable) {
                // A missing artifact is a fact about acquisition, not a reason to
                // abandon the remaining pages.
                $skipped++;

                continue;
            }

            if ($words === null) {
                $skipped++;

                continue;
            }

            [$recognized, $text, $dpi] = $words;

            if ($recognized === []) {
                $skipped++;

                continue;
            }

            if (trim($text) !== trim((string) $page->extracted_text)) {
                $differs++;
            }

            try {
                $page->forceFill([
                    'recognized_words' => $recognized,
                    'recognized_dpi' => $dpi,
                ])->save();
            } catch (Throwable) {
                // Losing one page to a write that could not get its turn is not
                // a reason to throw away the ninety already recovered.
                $skipped++;

                continue;
            }

            $filled++;
        }

        return ['considered' => $considered, 'filled' => $filled, 'skipped' => $skipped, 'text_differs' => $differs];
    }

    /**
     * @return LazyCollection<int, ExtractionPage>
     */
    private function pages(int $limit, bool $candidatesOnly): LazyCollection
    {
        $query = ExtractionPage::query()
            ->whereNull('recognized_words')
            ->whereIn('extraction_path', self::RASTER_PATHS);

        if ($candidatesOnly) {
            // Pages a reviewer is about to be shown come first; re-reading the
            // whole corpus is hours of work for structure most pages lack.
            $query->whereIn('id', ExtractionField::query()->whereNull('gold_source')->select('extraction_page_id'));
        }

        return $query->orderBy('id')->limit(max(1, $limit))->cursor();
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string, 2: int}|null
     */
    private function read(ExtractionPage $page): ?array
    {
        $artifact = $page->run?->artifactVersion;

        if (! $artifact instanceof SourceArtifactVersion) {
            return null;
        }

        // The DPI the page was originally read at, so the recovered boxes match
        // the text already stored beside them.
        $dpi = $page->extraction_path === 'ocr_enhanced'
            ? (int) config('civiclens.extraction.ocr.enhanced_dpi', 300)
            : (int) config('civiclens.extraction.ocr.primary_dpi', 150);

        $psm = $page->extraction_path === 'ocr_enhanced'
            ? (int) config('civiclens.extraction.ocr.enhanced_psm', 6)
            : (int) config('civiclens.extraction.ocr.primary_psm', 3);

        $materialized = null;
        $image = null;

        try {
            $materialized = $this->workspace->materialize($artifact);
            $image = $this->rasterizer->rasterize($materialized, (int) $page->page_number, $dpi);
            $result = $this->engine->recognize($image, $dpi, $psm);
        } finally {
            if ($image !== null) {
                $this->rasterizer->discard($image);
            }

            $this->workspace->discard($materialized);
        }

        $words = array_map(
            fn (RecognizedWord $word): array => [
                't' => $word->text,
                'c' => $word->confidence,
                'l' => $word->left,
                'y' => $word->top,
                'w' => $word->width,
                'h' => $word->height,
            ],
            $result->words,
        );

        return [$words, $result->text, $dpi];
    }
}
