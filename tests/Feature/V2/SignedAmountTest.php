<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Services\Extraction\FieldDecisionService;
use App\Services\Extraction\ReviewCandidateGenerator;
use App\Services\Extraction\SecondReadScorer;
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

it('will not certify a figure the two readings disagree the sign of', function (): void {
    // Edit distance called ১৯৩৭৮ against -১৯৩৭৮ a one-in-six difference and
    // scored it 0.156, inside every group's threshold. A sign is not a
    // character: that figure was published as a recovery where the page showed
    // a loss, and nineteen values on the corpus were accepted the same way.
    $scorer = app(SecondReadScorer::class);

    $method = new ReflectionMethod($scorer, 'signsDisagree');

    expect($method->invoke($scorer, '১৯৩৭৮', '-১৯৩৭৮'))->toBeTrue()
        ->and($method->invoke($scorer, '-১৯৩৭৮', '১৯৩৭৮'))->toBeTrue()
        ->and($method->invoke($scorer, '-১৯৩৭৮', '-১৯৩৭৮'))->toBeFalse()
        ->and($method->invoke($scorer, '১৯৩৭৮', '১৯৩৭৮'))->toBeFalse();
});

it('treats a typographic minus as the same claim as a hyphen', function (): void {
    // Two ways of writing one thing. Reading them as disagreement would fire on
    // every page that uses the typographic form.
    $scorer = app(SecondReadScorer::class);
    $method = new ReflectionMethod($scorer, 'signsDisagree');

    expect($method->invoke($scorer, '-১৯৩৭৮', "\u{2212}১৯৩৭৮"))->toBeFalse();
});

it('reads an accounting parenthesis as negative', function (): void {
    // (-২.৫১) means the same as -২.৫১, and the value keeps what the page shows.
    $scorer = app(SecondReadScorer::class);
    $method = new ReflectionMethod($scorer, 'signsDisagree');

    expect($method->invoke($scorer, '(-২.৫১)', '-২.৫১'))->toBeFalse();
});

it('defers a contested sign by rule, not by score', function (): void {
    // Scoring it 1.0 did nothing: the bn group's threshold is itself 1.0,
    // because its measured error rate sits inside alpha and conformal asks for
    // no threshold there at all. A score can only say "worse than most"; this
    // has to say "not at any level".
    $page = ExtractionPage::factory()->create([
        'extraction_path' => 'ocr_primary',
        'recognized_dpi' => 150,
        'recognized_words' => [['t' => '১৯৩৭৮', 'c' => 90.0, 'l' => 10, 'y' => 10, 'w' => 60, 'h' => 20]],
    ]);

    $field = ExtractionField::query()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => 'amount', 'field_type' => 'string',
        'extracted_value' => '১৯৩৭৮', 'script_class' => 'bn',
        'publisher_group' => 'dgcivil-bangladesh', 'confidence' => 84.0,
        'nonconformity_score' => 0.15, 'decision' => 'pending',
        'evidence_page_number' => 1, 'evidence_offset_start' => 0, 'evidence_offset_end' => 5,
    ]);

    // A null score defers whatever the threshold is, which is the property that
    // matters and the one a score could not provide.
    $field->forceFill(['nonconformity_score' => null, 'score_basis' => 'sign-disagreement'])->save();

    $decisions = app(FieldDecisionService::class)->decide(0.05);

    expect($field->fresh()->decision)->toBe('deferred')
        ->and($decisions)->toHaveKey('deferred');
});
