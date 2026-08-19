<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionTableCell;
use App\Models\Role;
use App\Models\User;
use App\Services\Extraction\EvidenceImagePainter;
use App\Services\Extraction\ValueLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  list<array{0: string, 1: int, 2: int}>  $words  text, left, top
 */
function pageWithWords(array $words): ExtractionPage
{
    return ExtractionPage::factory()->create([
        'extraction_path' => 'ocr_primary',
        'recognized_dpi' => 150,
        'recognized_words' => array_map(
            fn (array $w): array => ['t' => $w[0], 'c' => 90.0, 'l' => $w[1], 'y' => $w[2], 'w' => 60, 'h' => 20],
            $words,
        ),
    ]);
}

function fieldOn(ExtractionPage $page, string $value): ExtractionField
{
    return ExtractionField::query()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => 'amount', 'field_type' => 'string',
        'extracted_value' => $value, 'script_class' => 'bn',
        'publisher_group' => 'dgcivil-bangladesh', 'confidence' => 84.0,
        'nonconformity_score' => 0.16, 'decision' => 'pending',
        'evidence_page_number' => 1, 'evidence_offset_start' => 0, 'evidence_offset_end' => 5,
    ]);
}

it('finds where on the page the value was read from', function (): void {
    // A reviewer handed a budget table of hundreds of figures and asked whether
    // 100.00 matches cannot answer, because finding it is not the question.
    $page = pageWithWords([['১৫৬২৯', 100, 200], ['100.00', 400, 640], ['০.৫৪', 700, 900]]);

    $boxes = app(ValueLocator::class)->locate(fieldOn($page, '100.00'), $page);

    expect($boxes)->toHaveCount(1)
        ->and($boxes[0]['left'])->toBe(400)
        ->and($boxes[0]['top'])->toBe(640);
});

it('marks every place the characters appear rather than picking one', function (): void {
    // 27% of sampled values appear more than once on their own page. Choosing
    // one would claim a provenance the recognizer never recorded.
    $page = pageWithWords([['100.00', 400, 200], ['0.00', 400, 300], ['100.00', 400, 400]]);

    expect(app(ValueLocator::class)->locate(fieldOn($page, '100.00'), $page))->toHaveCount(2);
});

it('finds a value the recognizer split across words', function (): void {
    // Tesseract sometimes breaks a figure at its separator, and a value that
    // exists on the page must not be reported as absent because of that.
    $page = pageWithWords([['148,330', 300, 500], ['.05', 360, 500]]);

    $boxes = app(ValueLocator::class)->locate(fieldOn($page, '148,330.05'), $page);

    expect($boxes)->toHaveCount(1)
        ->and($boxes[0]['left'])->toBe(300)
        ->and($boxes[0]['right'])->toBe(420);
});

it('uses the cell box for a value that came from a table cell', function (): void {
    // The cell knows exactly where it is; searching the page cannot improve on
    // that and could land on a different copy of the same figure.
    $page = pageWithWords([['১৫৬২৯', 100, 200]]);
    $cell = ExtractionTableCell::factory()->create([
        'extraction_page_id' => $page->id, 'row_index' => 0, 'column_index' => 1,
        'text' => '১৫৬২৯', 'word_count' => 1,
        'box_left' => 325, 'box_top' => 1360, 'box_right' => 529, 'box_bottom' => 1399,
    ]);

    $field = fieldOn($page, '১৫৬২৯');
    $field->forceFill(['extraction_table_cell_id' => $cell->id])->save();

    $boxes = app(ValueLocator::class)->locate($field->fresh(), $page);

    expect($boxes)->toBe([['left' => 325, 'top' => 1360, 'right' => 529, 'bottom' => 1399]]);
});

it('reports nothing for a page read before word positions were stored', function (): void {
    $page = ExtractionPage::factory()->create(['recognized_words' => null]);

    expect(app(ValueLocator::class)->locate(fieldOn($page, '100.00'), $page))->toBe([]);
});

it('draws the mark outside the characters it points at', function (): void {
    // A mark over the characters hides exactly what is being judged.
    $blank = imagecreatetruecolor(200, 120);
    imagefill($blank, 0, 0, (int) imagecolorallocate($blank, 255, 255, 255));
    ob_start();
    imagepng($blank);
    $png = (string) ob_get_clean();
    imagedestroy($blank);

    $marked = app(EvidenceImagePainter::class)->highlight($png, [['left' => 80, 'top' => 50, 'right' => 120, 'bottom' => 70]]);
    $image = imagecreatefromstring($marked);

    $centre = imagecolorsforindex($image, imagecolorat($image, 100, 60));
    $edge = imagecolorsforindex($image, imagecolorat($image, 100, 45));

    expect($centre['red'])->toBe(255)
        ->and($centre['green'])->toBe(255)
        ->and($edge['red'])->toBe(220)
        ->and($edge['green'])->toBe(38);
});

it('shows the marked region as the thing to read, not the whole page', function (): void {
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $admin = User::factory()->create();
    $admin->roles()->attach($role);

    $page = pageWithWords([['100.00', 400, 640]]);
    fieldOn($page, '100.00');

    $this->actingAs($admin)->get('/admin/sources/review')
        ->assertOk()
        ->assertSee('Where it sits on the page')
        ->assertSee('crop=1', false)
        // The panel that showed OCR beside OCR is gone: it could only ever
        // agree with itself.
        ->assertDontSee('Recognized text around it');
});

it('finds a figure the page prints with a currency terminator after it', function (): void {
    // The amount pattern stops at the last digit, so the value is ৩,৯৮,৭৪৮
    // while the recognized word is ৩,৯৮,৭৪৮/-. Requiring an exact match made
    // 691 of 1,703 unlocatable values unlocatable, and an unlocatable value
    // defers by rule (ADR-019) — so the effect was to certify section numbers
    // off contents pages and withhold the sums of money.
    $page = pageWithWords([['৩,৯৮,৭৪৮/-', 400, 640]]);

    $boxes = app(ValueLocator::class)->locate(fieldOn($page, '৩,৯৮,৭৪৮'), $page);

    expect($boxes)->toHaveCount(1)
        ->and($boxes[0]['left'])->toBe(400);
});

it('accepts the other terminators these documents use', function (): void {
    foreach (['/=', '/', ':-'] as $terminator) {
        $page = pageWithWords([['৭১৬,৯৫৩'.$terminator, 400, 640]]);

        expect(app(ValueLocator::class)->locate(fieldOn($page, '৭১৬,৯৫৩'), $page))
            ->toHaveCount(1);
    }
});

it('still refuses a value whose separators sit differently on the page', function (): void {
    // The property the exact match was protecting. A thousands separator in the
    // wrong place is a hundredfold error and is exactly the misreading the
    // reviewer is being asked to judge, so it must not be normalised away.
    $page = pageWithWords([['৩,৯৮৭,৪৮/-', 400, 640]]);

    expect(app(ValueLocator::class)->locate(fieldOn($page, '৩,৯৮,৭৪৮'), $page))
        ->toBeEmpty();
});

it('does not treat a terminator as licence to match a longer number', function (): void {
    // ৩,৯৮,৭৪৮ must not match ৩,৯৮,৭৪৮৯ merely because trailing characters are
    // tolerated. Only the listed terminators are, and a digit is not one.
    $page = pageWithWords([['৩,৯৮,৭৪৮৯', 400, 640]]);

    expect(app(ValueLocator::class)->locate(fieldOn($page, '৩,৯৮,৭৪৮'), $page))
        ->toBeEmpty();
});

it('crops the value inside a cell rather than the whole cell', function (): void {
    // A cell box is the box of the cell: ruling lines, padding, and where the
    // grid is imperfect part of the next column. Re-reading one returned
    // "৪৭৪.২৫ | ৪৬,৮৩০" for a value of ৪৬,৮৩০.৮৬, and that disagreement
    // deferred the figure. The cell says where to look; the words say what to
    // crop.
    $page = pageWithWords([
        ['৪৭৪.২৫', 100, 200],
        ['৪৬,৮৩০.৮৬', 400, 200],
    ]);

    $field = fieldOn($page, '৪৬,৮৩০.৮৬');

    $cell = ExtractionTableCell::factory()->create([
        'extraction_page_id' => $page->id,
        // Wide enough to swallow the neighbouring column, as a real one does.
        'box_left' => 80,
        'box_top' => 190,
        'box_right' => 520,
        'box_bottom' => 240,
    ]);

    $field->forceFill(['extraction_table_cell_id' => $cell->id])->save();

    $boxes = app(ValueLocator::class)->locate($field->fresh(), $page);

    expect($boxes)->toHaveCount(1)
        ->and($boxes[0]['left'])->toBe(400)
        ->and($boxes[0]['right'])->toBe(460);
});

it('falls back to the cell box when the value cannot be matched inside it', function (): void {
    // The cell is still where the value is. A coarse crop beats no crop, which
    // would defer the figure by rule.
    $page = pageWithWords([['something else', 100, 200]]);

    $field = fieldOn($page, '৪৬,৮৩০.৮৬');

    $cell = ExtractionTableCell::factory()->create([
        'extraction_page_id' => $page->id,
        'box_left' => 80,
        'box_top' => 190,
        'box_right' => 520,
        'box_bottom' => 240,
    ]);

    $field->forceFill(['extraction_table_cell_id' => $cell->id])->save();

    $boxes = app(ValueLocator::class)->locate($field->fresh(), $page);

    expect($boxes)->toHaveCount(1)
        ->and($boxes[0]['left'])->toBe(80)
        ->and($boxes[0]['right'])->toBe(520);
});

it('does not take a word from the neighbouring column into the cell', function (): void {
    // Centre, not overlap: a word clipped by the cell edge belongs to whichever
    // cell holds most of it.
    $page = pageWithWords([
        ['১২,৩৪৫', 500, 200],
    ]);

    $field = fieldOn($page, '১২,৩৪৫');

    $cell = ExtractionTableCell::factory()->create([
        'extraction_page_id' => $page->id,
        // The word starts at 500 and is 60 wide, so its centre is 530 — outside.
        'box_left' => 80,
        'box_top' => 190,
        'box_right' => 520,
        'box_bottom' => 240,
    ]);

    $field->forceFill(['extraction_table_cell_id' => $cell->id])->save();

    $boxes = app(ValueLocator::class)->locate($field->fresh(), $page);

    expect($boxes[0]['left'])->toBe(80);
});
