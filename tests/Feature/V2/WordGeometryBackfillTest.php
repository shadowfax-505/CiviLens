<?php

use App\Data\Extraction\OcrPageResult;
use App\Data\Extraction\RecognizedWord;
use App\Models\ExtractionPage;
use App\Models\ExtractionTableCell;
use App\Models\SourceArtifactVersion;
use App\Services\Extraction\ArtifactWorkspace;
use App\Services\Extraction\PageRasterizer;
use App\Services\Extraction\PageTableAssembler;
use App\Services\Extraction\TableStructureDetector;
use App\Services\Extraction\TesseractOcrEngine;
use App\Services\Extraction\WordGeometryBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Stands in for poppler: records the DPI it was asked to render at. */
function fakeRasterizer(): PageRasterizer
{
    return new class extends PageRasterizer
    {
        /** @var list<int> */
        public array $requested = [];

        public function rasterize(string $pdfPath, int $pageNumber, int $dpi): string
        {
            $this->requested[] = $dpi;

            return $pdfPath;
        }

        public function discard(?string $path): void {}
    };
}

function fakeWorkspace(): ArtifactWorkspace
{
    return new class extends ArtifactWorkspace
    {
        public function materialize(SourceArtifactVersion $artifact): string
        {
            return '/tmp/not-a-real-artifact.pdf';
        }

        public function discard(?string $path): void {}
    };
}

function fakeEngine(string $text, float $confidence = 88.0): TesseractOcrEngine
{
    return new class($text, $confidence) extends TesseractOcrEngine
    {
        public function __construct(private readonly string $said, private readonly float $confidence) {}

        public function recognize(string $imagePath, int $dpi, int $pageSegmentationMode): OcrPageResult
        {
            return new OcrPageResult(
                [new RecognizedWord($this->said, $this->confidence, 10, 20, 30, 12)],
                $this->said, $this->confidence, 1, 'tesseract', '5.5.0', 'ben+eng', $dpi, 5,
            );
        }
    };
}

it('recovers word boxes without touching the text a reviewer may have judged', function (): void {
    // Text is the thing labels were made against. Replacing it underneath a
    // label would invalidate the label with nothing on screen saying so.
    $page = ExtractionPage::factory()->create([
        'extraction_path' => 'ocr_primary',
        'extracted_text' => 'as it was first read',
        'confidence' => 84.0,
        'recognized_words' => null,
    ]);

    app()->instance(ArtifactWorkspace::class, fakeWorkspace());
    app()->instance(PageRasterizer::class, fakeRasterizer());
    app()->instance(TesseractOcrEngine::class, fakeEngine('read differently this time'));

    $summary = app(WordGeometryBackfill::class)->backfill(10, candidatesOnly: false);

    expect($summary['filled'])->toBe(1)
        // The disagreement is counted and reported, never acted on.
        ->and($summary['text_differs'])->toBe(1);

    $page->refresh();

    expect($page->extracted_text)->toBe('as it was first read')
        ->and($page->confidence)->toEqual(84.0)
        ->and($page->recognized_words)->toHaveCount(1)
        ->and($page->recognized_dpi)->toBe(150);
});

it('re-reads an enhanced page at the DPI it was first read at', function (): void {
    // A page read by the enhanced pass carries boxes at 300. Recovering them at
    // 150 would halve every coordinate and place the words nowhere near the text.
    $page = ExtractionPage::factory()->create([
        'extraction_path' => 'ocr_enhanced',
        'extracted_text' => 'faint scan',
        'recognized_words' => null,
    ]);

    $rasterizer = fakeRasterizer();
    app()->instance(ArtifactWorkspace::class, fakeWorkspace());
    app()->instance(PageRasterizer::class, $rasterizer);
    app()->instance(TesseractOcrEngine::class, fakeEngine('faint scan'));

    app(WordGeometryBackfill::class)->backfill(10, candidatesOnly: false);

    expect($rasterizer->requested)->toBe([300])
        ->and($page->refresh()->recognized_dpi)->toBe(300);
});

it('renders a page for table detection at the DPI its words were measured at', function (): void {
    // Cell boxes and word boxes have to share one coordinate space. Assuming the
    // primary DPI puts every word of an enhanced page in the wrong cell, or none.
    $page = ExtractionPage::factory()->create([
        'extraction_path' => 'ocr_enhanced',
        'recognized_dpi' => 300,
        'recognized_words' => [['t' => '১৫৬২৯', 'c' => 91.0, 'l' => 100, 'y' => 200, 'w' => 40, 'h' => 14]],
    ]);

    $rasterizer = fakeRasterizer();
    app()->instance(ArtifactWorkspace::class, fakeWorkspace());
    app()->instance(PageRasterizer::class, $rasterizer);
    app()->instance(TableStructureDetector::class, new class extends TableStructureDetector
    {
        public function detect(string $imagePath): array
        {
            return [['index' => 0, 'cells' => [
                ['row' => 0, 'col' => 0, 'row_span' => 1, 'col_span' => 1, 'box' => [90, 190, 160, 220], 'model_text' => ''],
            ]]];
        }
    });

    $summary = app(PageTableAssembler::class)->assemble($page);

    expect($rasterizer->requested)->toBe([300])
        ->and($summary['cells'])->toBe(1)
        ->and($summary['words_placed'])->toBe(1)
        ->and(ExtractionTableCell::query()->first()->text)->toBe('১৫৬২৯');
});
