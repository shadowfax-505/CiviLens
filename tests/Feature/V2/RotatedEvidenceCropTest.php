<?php

use App\Models\ExtractionPage;
use App\Services\Extraction\PageFrame;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function landscapeImage(): string
{
    $canvas = imagecreatetruecolor(400, 200);
    imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
    $path = sys_get_temp_dir().'/civiclens-frame-'.bin2hex(random_bytes(6)).'.png';
    imagepng($canvas, $path);

    return $path;
}

it('turns a rendered page into the frame its boxes were measured in', function (): void {
    // A sideways page is straightened before it is read, so its geometry is in
    // the rotated frame. Rendering the stored orientation and drawing a box on
    // it rings the wrong part of the page with total confidence — the same
    // failure as rendering at the wrong DPI, which produced a crop one pixel
    // wide on 24 pages, four already adjudicated from that screen.
    $path = landscapeImage();
    $page = ExtractionPage::factory()->create(['rotation_applied' => 90]);

    app(PageFrame::class)->align($path, $page);

    [$width, $height] = getimagesize($path) ?: [0, 0];
    @unlink($path);

    // 400x200 turned a quarter is 200x400.
    expect($width)->toBe(200)->and($height)->toBe(400);
});

it('leaves a page that was read as stored alone', function (): void {
    // Every page read before straightening existed, which is most of the
    // corpus. Turning one of those would break what currently works.
    $path = landscapeImage();
    $page = ExtractionPage::factory()->create(['rotation_applied' => null]);

    app(PageFrame::class)->align($path, $page);

    [$width, $height] = getimagesize($path) ?: [0, 0];
    @unlink($path);

    expect($width)->toBe(400)->and($height)->toBe(200);
});

it('invents no turn for a page it cannot resolve', function (): void {
    $path = landscapeImage();

    app(PageFrame::class)->align($path, null);

    [$width, $height] = getimagesize($path) ?: [0, 0];
    @unlink($path);

    expect($width)->toBe(400)->and($height)->toBe(200);
});
