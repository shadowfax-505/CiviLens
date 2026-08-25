<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Services\Extraction\ReviewCandidateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return list<string> */
function amountsFrom(string $text): array
{
    ExtractionPage::factory()->create([
        'extracted_text' => $text,
        'extraction_path' => 'ocr_primary',
        'character_count' => max(51, mb_strlen($text)),
        'script_class' => 'bn',
        'confidence' => 84.0,
    ]);

    app(ReviewCandidateGenerator::class)->generate(5);

    return ExtractionField::query()->where('field_key', 'amount')->pluck('extracted_value')->all();
}

it('does not read a fragment out of a number it cannot match whole', function (): void {
    // The recognizer read the first thousands separator as a point, so the
    // pattern could not take ৬.০২,৪৫,৩৯৫ as one figure — and then started
    // again after the next comma and produced ৪৫,৩৯৫, which is not a number
    // the page contains. A reviewer shown it is being asked about a figure
    // that is not there.
    $values = amountsFrom('নগদ সহায়তা প্রদান করায় ৬.০২,৪৫,৩৯৫/- টাকা রাজস্ব ক্ষতি হয়েছে যা আদায়যোগ্য');

    expect($values)->not->toContain('৪৫,৩৯৫');
});

it('does not truncate a section number into an amount', function (): void {
    // ৩.৬.৭ is the number of a section. Taking ৩.৬ from it invents a figure.
    expect(amountsFrom('৩.৬.৭ প্রাথমিক ও গণশিক্ষা মন্ত্রণালয় (মঞ্জুরি ২০) এর হিসাব বিবরণী নিম্নরূপ'))
        ->not->toContain('৩.৬');
});

it('does not truncate a figure written with three decimal places', function (): void {
    // ৩,৮৫.৯৯৪ recorded as ৩,৮৫ is wrong by three orders of magnitude.
    // Producing nothing is the right answer: the shape is not one this pattern
    // handles, and silence is not a claim.
    expect(amountsFrom('হিসাবে ৩,৮৫.৯৯৪/- টাকা জমা রয়েছে বলে প্রতিবেদনে উল্লেখ করা হয়েছে এই খাতে'))
        ->not->toContain('৩,৮৫');
});

it('reads a Bengali-digit decimal as one figure', function (): void {
    // The fractional part used to accept only Latin digits while the integer
    // part accepted both, which is what sent ৬.০২ down the fragment path.
    expect(amountsFrom('মোট ব্যয় ১১.৫৩ শতাংশ বৃদ্ধি পেয়েছে গত অর্থবছরের তুলনায় এই খাতে সর্বমোট'))
        ->toContain('১১.৫৩');
});

it('does not read the result of a subtraction as a negative figure', function (): void {
    // These reports write a difference as (A-B)-C, where the last minus
    // introduces the result. Reading it as a sign reverses the figure.
    $values = amountsFrom('উদ্বৃত্ত হওয়ায় (১,৫৯৪.৫২-১,১১৬.৮২)-৪৭৭.৭০ কোটি টাকা রয়ে গেছে এই খাতে');

    expect($values)->toContain('৪৭৭.৭০')
        ->and($values)->not->toContain('-৪৭৭.৭০');
});

it('still reads an ordinary grouped figure whole', function (): void {
    expect(amountsFrom('মোট ৬,০২,৪৫,৩৯৫/- টাকা রাজস্ব ক্ষতি হয়েছে যা আদায়যোগ্য বলে প্রতিবেদনে বলা হয়'))
        ->toContain('৬,০২,৪৫,৩৯৫');
});

it('still reads a figure that follows a comma and a space', function (): void {
    // The guard blocks a match starting *immediately* after a comma, which is
    // mid-number. A comma in prose is followed by a space.
    expect(amountsFrom('রাজস্ব খাতে, ১২,৩৪৫ টাকা ব্যয় হয়েছে বলে প্রতিবেদনে উল্লেখ করা হয়েছে এই বছরে'))
        ->toContain('১২,৩৪৫');
});

it('counts an unreadable page apart from one that has nothing left to give', function (): void {
    // Reported as one number, these opposite states are indistinguishable: a
    // corpus already harvested to its last figure looks like a corpus the
    // reader could not read. That reading cost real time.
    ExtractionPage::factory()->create([
        'extracted_text' => str_repeat('স ূ ড ি ত ্ র ে া ি ু ', 40),
        'extraction_path' => 'native',
        'character_count' => 500,
        'script_class' => 'bn',
        'confidence' => 84.0,
    ]);

    $first = app(ReviewCandidateGenerator::class)->generate(50);

    expect($first['unreadable_pages'])->toBe(1)
        ->and($first['pages_without_new_candidates'])->toBe(0);

    // A readable page, harvested twice: the second pass finds nothing new and
    // that is not the same as being unable to read it.
    ExtractionPage::factory()->create([
        'extracted_text' => 'মোট ১,২৩,৪৫৬ টাকা ব্যয় হয়েছে বলে প্রতিবেদনে উল্লেখ করা হয়েছে এই অর্থবছরে',
        'extraction_path' => 'ocr_primary',
        'character_count' => 120,
        'script_class' => 'bn',
        'confidence' => 84.0,
    ]);

    app(ReviewCandidateGenerator::class)->generate(50);
    $third = app(ReviewCandidateGenerator::class)->generate(50);

    expect($third['created'])->toBe(0)
        ->and($third['unreadable_pages'])->toBe(1)
        ->and($third['pages_without_new_candidates'])->toBe(1);
});

it('does not read an equals sign as a negative', function (): void {
    // These reports write a difference as (A-B) = C, and the recognizer reads
    // that equals sign as a hyphen. 313,542 − 281,417 is 32,125 exactly, so the
    // figure is positive; reading it as negative turns a recovery into a loss.
    //
    // The second read cannot catch it — same engine family, same glyph, agrees
    // with itself — so it certifies the wrong sign. A reviewer on the page is
    // the only thing that sees it.
    expect(amountsFrom('জমা প্রদান করায় (৩,১৩,৫৪২-২,৮১,৪১৭) -৩২,১২৫ টাকা আদায়যোগ্য বলে প্রতিবেদনে'))
        ->toContain('৩২,১২৫')
        ->and(amountsFrom('জমা প্রদান করায় (৩,১৩,৫৪২-২,৮১,৪১৭) -৩২,১২৫ টাকা আদায়যোগ্য বলে প্রতিবেদনে'))
        ->not->toContain('-৩২,১২৫');
});

it('reads the result marker whether or not a space follows the bracket', function (): void {
    expect(amountsFrom('ফলে (৩,৩৩,৪৪২.৬৮-৭০,৬৪১)-২,৬২,৭৮২ টাকা আদায়যোগ্য বলে প্রতিবেদনে উল্লেখ আছে'))
        ->not->toContain('-২,৬২,৭৮২');
});

it('keeps a genuine negative', function (): void {
    // Reappropriation columns run negative throughout this corpus — 385 of the
    // 425 values that start with a minus. Stripping those would invent money.
    expect(amountsFrom('পুনঃ উপযোজন খাতে ০ -২৫.২৫ ০ -১০.৭ ০ ০ কমোট মঞ্জুরি বরাদ্দ হয়েছে এই খাতে'))
        ->toContain('-২৫.২৫')
        ->toContain('-১০.৭');
});

it('keeps a negative that merely follows a bracketed figure elsewhere in the line', function (): void {
    // The bracket has to be what the minus follows. A bracket earlier in the
    // line is not a result marker, and treating it as one would strip signs off
    // a whole table row.
    expect(amountsFrom('তহবিল (বিবরণী-০৫) খাতে মোট ৭০,১৪০ এবং -২৮,২১৯ টাকা সমন্বয় করা হয়েছে বলে'))
        ->toContain('-২৮,২১৯');
});
