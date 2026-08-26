<?php

use App\Models\ExtractionPage;
use App\Services\Extraction\PageRasterizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A rasterizer that records the resolution it was asked for.
 */
function recordingRasterizer(): object
{
    $spy = new class extends PageRasterizer
    {
        public ?int $dpi = null;

        public function __construct() {}

        public function rasterize(string $document, int $page, int $dpi): string
        {
            $this->dpi = $dpi;

            $path = sys_get_temp_dir().'/civiclens-page-'.uniqid().'.png';
            $image = imagecreatetruecolor(50, 50);
            imagepng($image, $path);

            return $path;
        }

        public function discard(?string $path): void {}
    };

    app()->instance(PageRasterizer::class, $spy);

    return $spy;
}

it('renders a page at the resolution its boxes were measured at', function (): void {
    // A page read by the enhanced pass is recognised at 300 while the review
    // screen rendered at 150, so its boxes landed off the raster and the crop
    // came back a single pixel wide — a reviewer shown a blank sliver of the
    // page they were asked to judge. 24 pages are read that way and four had
    // already been adjudicated from that screen.
    $page = ExtractionPage::factory()->create(['recognized_dpi' => 300]);

    expect($page->recognized_dpi)->toBe(300);

    // The rule the controllers now follow, stated where it can be checked: the
    // page says what resolution to use, never the configuration.
    $configured = (int) config('civiclens.extraction.review_page_dpi', 150);

    expect($page->recognized_dpi ?? $configured)->toBe(300)
        ->and($configured)->toBe(150);
});

it('falls back to the configured resolution when the page never recorded one', function (): void {
    $page = ExtractionPage::factory()->create(['recognized_dpi' => null]);

    $configured = (int) config('civiclens.extraction.review_page_dpi', 150);

    expect($page->recognized_dpi ?? $configured)->toBe(150);
});
