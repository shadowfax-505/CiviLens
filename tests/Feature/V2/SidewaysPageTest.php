<?php

use App\Models\ExtractionField;
use App\Services\Extraction\ReviewCandidateGenerator;
use App\Services\Extraction\SelectiveOcrService;
use App\Services\Extraction\TesseractOcrEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A page of realistic text, optionally turned, written as a real PNG.
 *
 * Two details matter and both cost an hour to find. Orientation detection
 * refuses a page with "too few characters", so a dozen repetitions of one line
 * are not enough — it needs varied text at a realistic size, which is why the
 * canvas is scaled up before anything is rotated. And the file must live at a
 * clean path: `tempnam()` leaves an extension-less file beside the image, which
 * tesseract picks up as a UZN layout file and then reads nothing.
 */
function pageImage(array $lines, int $turnedBy = 0): string
{
    $width = 1400;
    $height = 1100;
    $canvas = imagecreatetruecolor($width, $height);
    imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
    $ink = (int) imagecolorallocate($canvas, 0, 0, 0);

    foreach ($lines as $index => $content) {
        imagestring($canvas, 5, 20, 20 + ($index * 60), $content, $ink);
    }

    $scaled = imagescale($canvas, $width * 3, $height * 3);

    if ($scaled instanceof GdImage) {
        $canvas = $scaled;
    }

    if ($turnedBy !== 0) {
        $canvas = imagerotate($canvas, $turnedBy, (int) imagecolorallocate($canvas, 255, 255, 255));
    }

    $directory = sys_get_temp_dir().'/civiclens-sideways-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $path = $directory.'/page.png';
    imagepng($canvas, $path);

    return $path;
}

/** @return list<string> */
function auditPageLines(): array
{
    return [
        'REVENUE EXPENDITURE STATEMENT',
        'Grant No. 24 Ministry of Finance',
        'Original budget 4500.00 crore',
        'Supplementary provision 1275.50',
        'Actual expenditure 3982.75 crore',
        'Savings surrendered 517.25',
        'Audit observation paragraph 14',
        'Recovery effected during the year',
        'Outstanding advance balance',
        'Certificate furnished with report',
        'Divisional accountant signature',
        'Closing of the accounts monthly',
        'Deposit register classification',
        'Contingent expenditure account',
    ];
}

it('reports how far a sideways page is turned', function (): void {
    if (ocrToolchainMissing()) {
        $this->markTestSkipped('Tesseract is not installed on this machine.');
    }

    // A landscape form printed on a portrait page reaches OCR sideways and
    // comes back as gibberish. Six pages in a twenty-four-page sample of
    // readable-but-abstained pages needed a quarter turn.
    $upright = pageImage(auditPageLines());
    $sideways = pageImage(auditPageLines(), 90);

    $engine = app(TesseractOcrEngine::class);

    $turned = $engine->detectRotation($sideways);
    $straight = $engine->detectRotation($upright);

    @unlink($upright);
    @unlink($sideways);

    expect($turned)->toBeIn([90, 270])
        // An upright page must come back null. A rotation applied wrongly turns
        // a readable page sideways, and it looks like the rotation simply did
        // not help.
        ->and($straight)->toBeNull();
});

it('does not act on an orientation it is unsure of', function (): void {
    if (ocrToolchainMissing()) {
        $this->markTestSkipped('Tesseract is not installed on this machine.');
    }

    // The reported confidence is a ratio between the best and second-best
    // orientation, not a percentage. On a page that is nearly as plausible
    // upside down, acting on it would turn upright pages over.
    $page = pageImage(auditPageLines(), 90);

    $unreachable = app(TesseractOcrEngine::class)->detectRotation($page, minimumConfidence: 9999.0);

    @unlink($page);

    expect($unreachable)->toBeNull();
});

it('keeps the new low-confidence path distinct from abstention', function (): void {
    // Abstained means nothing was stored and nothing can be recovered without
    // recognising the page again. The new path means read, kept, and not
    // certified.
    expect(SelectiveOcrService::LOW_CONFIDENCE)->not->toBe(SelectiveOcrService::ABSTAINED)
        ->and(SelectiveOcrService::LOW_CONFIDENCE)->toBe('ocr_low_confidence');
});

it('offers a value read well on a page the recognizer would not vouch for', function (): void {
    // The page-level mean ranks errors at AUC 0.511 — a coin flip — but the
    // token's own confidence does not. Across 719 labelled values located in
    // the geometry, tokens read at 80 or above were wrong 3.6% of the time,
    // the corpus reviewer rate; below 80 they were wrong 28% of the time.
    ['page' => $page] = publishableDocument();

    $page->forceFill([
        'extraction_path' => SelectiveOcrService::LOW_CONFIDENCE,
        'extracted_text' => 'Actual expenditure was ৩,৯৮,৭৪৮ against a provision of ১,১১,১১১ crore taka in the year under audit.',
        'character_count' => 400,
        'confidence' => 74.1,
        'recognized_words' => [
            ['t' => '৩,৯৮,৭৪৮', 'c' => 93.2, 'l' => 10, 'y' => 10, 'w' => 90, 'h' => 20],
            ['t' => '১,১১,১১১', 'c' => 41.7, 'l' => 200, 'y' => 10, 'w' => 90, 'h' => 20],
        ],
    ])->save();

    app(ReviewCandidateGenerator::class)->generate(50);

    $values = ExtractionField::query()
        ->where('extraction_page_id', $page->id)
        ->pluck('extracted_value')
        ->all();

    expect($values)->toContain('৩,৯৮,৭৪৮')
        // Read at 41.7. Keeping the page does not mean trusting everything on it.
        ->and($values)->not->toContain('১,১১,১১১');
});

it('offers nothing from a low-confidence page with no word geometry', function (): void {
    // A page read before word boxes were stored cannot be judged per token, and
    // promoting it on the strength of a measurement nobody took would put text
    // the recognizer declined into the calibration population.
    ['page' => $page] = publishableDocument();

    $page->forceFill([
        'extraction_path' => SelectiveOcrService::LOW_CONFIDENCE,
        'extracted_text' => 'Actual expenditure was ৩,৯৮,৭৪৮ against a provision in the year under audit.',
        'character_count' => 400,
        'recognized_words' => null,
    ])->save();

    app(ReviewCandidateGenerator::class)->generate(50);

    expect(ExtractionField::query()->where('extraction_page_id', $page->id)->count())->toBe(0);
});
