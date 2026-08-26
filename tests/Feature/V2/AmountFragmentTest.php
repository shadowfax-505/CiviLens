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

it('keeps a negative that follows a percentage in brackets', function (): void {
    // The shape of every ordinary budget row: a percentage in parentheses sits
    // before the next column's figure. Testing only for a closing bracket
    // stripped the sign off eight genuine negatives before a reviewer's
    // screenshot caught it — this is that case.
    expect(amountsFrom('সরকারি ঋণ পরিশোধ ১৪০১১৫ (১৭.৩৮) -১৯৩৭৮ (-১৬.০৫) ১৮৭৯০ বলে প্রতিবেদনে উল্লেখ'))
        ->toContain('-১৯৩৭৮');
});

it('keeps a negative that follows a bracketed single figure', function (): void {
    expect(amountsFrom('তহবিল হতে ৪৪৩১৭৮ (৫৩১৮৫) (৩৯৮.৭৭) -২৮২১৯৬ টাকা সমন্বয় করা হয়েছে বলে জানা যায়'))
        ->toContain('-২৮২১৯৬');
});

it('reads the answer to a sum written after it', function (): void {
    // 19,67,799 + 2,24,295 + 2,78,801 is 24,70,895 exactly, so the mark before
    // the answer is an equals sign however many terms the bracket holds.
    expect(amountsFrom('আর্থিক ক্ষতি (১৯,৬৭,৭৯৯+২,২৪,২৯৫+২,৭৮,৮০১) -২৪,৭০,৮৯৫ টাকা আদায়যোগ্য বলে'))
        ->toContain('২৪,৭০,৮৯৫')
        ->not->toContain('-২৪,৭০,৮৯৫');
});

it('reads a firm name introduced as Messrs', function (): void {
    // মেসার্স and M/S mean "the firm of", so what follows is a name rather than
    // a description of one. This is what gives the screener Bangladeshi names
    // to compare its 1,401 debarment entities against.
    ExtractionPage::factory()->create([
        'extracted_text' => 'অনিয়মিতভাবে M/S SKYNET SEA FOODS কে নগদ সহায়তা প্রদান করা হয়েছে বলে প্রতিবেদনে উল্লেখ',
        'extraction_path' => 'ocr_primary',
        'character_count' => 200,
        'script_class' => 'bn',
        'confidence' => 84.0,
    ]);

    app(ReviewCandidateGenerator::class)->generate(5);

    expect(ExtractionField::query()->where('field_key', 'organisation')->pluck('extracted_value')->all())
        ->toContain('M/S SKYNET SEA FOODS');
});

it('ends a firm name at the particle that follows it', function (): void {
    // এর and কে mark the noun before them, so what comes after is the sentence
    // resuming rather than more of the name.
    ExtractionPage::factory()->create([
        'extracted_text' => 'প্রতিষ্ঠান মেসার্স সিমার্ক এর নিকট হইতে আদায়যোগ্য অর্থ এখনো আদায় করা হয়নি বলে জানা যায়',
        'extraction_path' => 'ocr_primary',
        'character_count' => 200,
        'script_class' => 'bn',
        'confidence' => 84.0,
    ]);

    app(ReviewCandidateGenerator::class)->generate(5);

    expect(ExtractionField::query()->where('field_key', 'organisation')->pluck('extracted_value')->all())
        ->toContain('মেসার্স সিমার্ক');
});

it('does not call a description of a bank the name of one', function (): void {
    // Anchoring on ব্যাংক or কোম্পানি was tried across the whole corpus and
    // returned "various commercial banks" and "and corporation". A regex does
    // not find names in Bengali prose, and a reviewer asked to judge those is
    // being asked the wrong question.
    ExtractionPage::factory()->create([
        'extracted_text' => 'বিভিন্ন বাণিজ্যিক ব্যাংক হইতে ঋণ গ্রহণ করা হইয়াছে এবং কর্পোরেশন কর্তৃক তাহা পরিশোধ করা হয় নাই',
        'extraction_path' => 'ocr_primary',
        'character_count' => 200,
        'script_class' => 'bn',
        'confidence' => 84.0,
    ]);

    app(ReviewCandidateGenerator::class)->generate(5);

    expect(ExtractionField::query()->where('field_key', 'organisation')->count())->toBe(0);
});
