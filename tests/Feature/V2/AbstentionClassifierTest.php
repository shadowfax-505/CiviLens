<?php

use App\Models\ExtractionPage;
use App\Services\Extraction\AbstentionClassifier;
use App\Services\Extraction\ArtifactWorkspace;
use App\Services\Extraction\PageRasterizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A rasterizer that paints a page of a given darkness, so the classifier is
 * tested on what it measures rather than on a PDF fixture.
 */
function rasterizerPainting(float $inkShare): void
{
    $path = sys_get_temp_dir().'/civiclens-test-'.uniqid().'.png';

    $image = imagecreatetruecolor(120, 120);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);

    if ($inkShare > 0.0) {
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, 119, (int) round(119 * $inkShare), $black);
    }

    imagepng($image, $path);

    app()->instance(ArtifactWorkspace::class, new class extends ArtifactWorkspace
    {
        public function __construct() {}

        public function materialize($artifact): string
        {
            return 'unused.pdf';
        }

        public function discard(?string $path): void {}
    });

    app()->instance(PageRasterizer::class, new class($path) extends PageRasterizer
    {
        public function __construct(private readonly string $painted) {}

        public function rasterize(string $document, int $page, int $dpi): string
        {
            return $this->painted;
        }

        public function discard(?string $path): void {}
    });
}

function abstainedPage(?float $confidence): ExtractionPage
{
    return ExtractionPage::factory()->create([
        'extraction_path' => 'abstained',
        'confidence' => $confidence,
        'recognized_dpi' => 150,
        'abstention_reason' => null,
    ]);
}

it('calls a page with no ink on it blank', function (): void {
    // 198 abstentions carried the same sentence as a page the reader failed on,
    // and were blank. A blank separator page abstained on is the system
    // working, and counting it as a failure overstates the difficulty of the
    // corpus.
    rasterizerPainting(0.0);
    abstainedPage(null);

    expect(app(AbstentionClassifier::class)->classify())
        ->toBe([AbstentionClassifier::BLANK => 1]);
});

it('separates a page read just below the threshold', function (): void {
    // The only band where a third pass at higher resolution is worth its cost.
    rasterizerPainting(0.5);
    abstainedPage(77.0);

    expect(app(AbstentionClassifier::class)->classify())
        ->toBe([AbstentionClassifier::NEAR_MISS => 1]);
});

it('separates a page read far below the threshold', function (): void {
    rasterizerPainting(0.5);
    abstainedPage(45.0);

    expect(app(AbstentionClassifier::class)->classify())
        ->toBe([AbstentionClassifier::UNREADABLE => 1]);
});

it('calls ink with no confidence a failure to read, not an empty page', function (): void {
    // Something is on the page and the recognizer returned nothing from it.
    rasterizerPainting(0.5);
    abstainedPage(null);

    expect(app(AbstentionClassifier::class)->classify())
        ->toBe([AbstentionClassifier::UNREADABLE => 1]);
});

it('does not classify the same page twice', function (): void {
    rasterizerPainting(0.0);
    abstainedPage(null);

    app(AbstentionClassifier::class)->classify();

    expect(app(AbstentionClassifier::class)->classify())->toBe([]);
});
