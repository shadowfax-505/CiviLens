<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Services\Extraction\ReviewCandidateGenerator;
use App\Services\Extraction\ValueLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pageSaying(string $text): ExtractionPage
{
    return ExtractionPage::factory()->create([
        'extracted_text' => $text,
        'extraction_path' => 'ocr_primary',
        'character_count' => max(51, mb_strlen($text)),
        'script_class' => 'bn',
        'confidence' => 84.0,
    ]);
}

/** @return list<string> */
function valuesFrom(string $text): array
{
    pageSaying($text);
    app(ReviewCandidateGenerator::class)->generate(5);

    return ExtractionField::query()->where('field_key', 'amount')->pluck('extracted_value')->all();
}

it('keeps the minus sign with the figure', function (): void {
    // Read without it, a reappropriation of -১০০.০০ crore is recorded as
    // +১০০.০০ and the reviewer is shown a value the page does not contain.
    // 254 words across 110 pages carry a leading minus.
    expect(valuesFrom('পুন ঃ উপযোজন -১০০.০০ কোটি টাকা বরাদ্দ হয়েছে এই খাতে মোট ব্যয়'))
        ->toContain('-১০০.০০');
});

it('keeps accounting parentheses, which mean the same thing', function (): void {
    // 240 words are written this way. The value keeps what the page shows and
    // does not reinterpret it.
    expect(valuesFrom('প্রবৃদ্ধি হার (-২.৫১) শতাংশ কম ব্যয় হয়েছে গত অর্থবছরের তুলনায় এই খাতে'))
        ->toContain('(-২.৫১)');
});

it('does not turn a year range into a negative number', function (): void {
    // 2013-2017 must not yield -2017. A minus directly after a digit is a
    // range, not a sign.
    $values = valuesFrom('২০১৩-২০১৭ অর্থবছরে মোট ১,২৩,৪৫৬ টাকা ব্যয় করা হয়েছে বলে জানা যায়');

    expect($values)->toContain('১,২৩,৪৫৬')
        ->and($values)->not->toContain('-২০১৭');
});

it('does not turn the second half of a range of figures into a negative', function (): void {
    $values = valuesFrom('পৃষ্ঠা ১২,৩৪৫-৬৭,৮৯০ পর্যন্ত বিস্তারিত বিবরণ দেওয়া হয়েছে পরিশিষ্টে');

    expect($values)->toContain('১২,৩৪৫')
        ->and($values)->toContain('৬৭,৮৯০')
        ->and($values)->not->toContain('-৬৭,৮৯০');
});

it('records a bracketed figure as negative in the normalised value', function (): void {
    // The value keeps what the page shows; this is what later analysis adds up,
    // and a bracketed figure summed as positive is a sign error in arithmetic.
    valuesFrom('মোট ঘাটতি (১২,৩৪৫.৬৭) টাকা হিসাবে দেখানো হয়েছে এই অর্থবছরের বিবরণীতে');

    $field = ExtractionField::query()->where('extracted_value', '(১২,৩৪৫.৬৭)')->firstOrFail();

    expect($field->normalized_value)->toBe('-১২৩৪৫.৬৭');
});

it('marks the sign as part of the value on the page', function (): void {
    // The reviewer's complaint: the box sat on the digits and left the minus
    // outside it, so the marked characters were not the value being judged.
    $page = ExtractionPage::factory()->create([
        'script_class' => 'bn',
        'recognized_dpi' => 150,
        'recognized_words' => [
            ['t' => '-', 'c' => 80.0, 'l' => 380, 'y' => 640, 'w' => 12, 'h' => 18],
            ['t' => '১০০.০০', 'c' => 91.0, 'l' => 396, 'y' => 640, 'w' => 64, 'h' => 20],
        ],
    ]);

    $field = ExtractionField::query()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => 'amount', 'field_type' => 'string',
        'extracted_value' => '-১০০.০০', 'script_class' => 'bn',
        'publisher_group' => 'dgcivil-bangladesh', 'confidence' => 84.0,
        'nonconformity_score' => 0.16, 'decision' => 'pending',
        'evidence_page_number' => 1, 'evidence_offset_start' => 0, 'evidence_offset_end' => 7,
    ]);

    $boxes = app(ValueLocator::class)->locate($field, $page);

    // One box spanning both, starting at the minus rather than at the digits.
    expect($boxes)->toHaveCount(1)
        ->and($boxes[0]['left'])->toBe(380)
        ->and($boxes[0]['right'])->toBe(460);
});
