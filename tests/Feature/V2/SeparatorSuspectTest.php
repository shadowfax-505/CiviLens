<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\Role;
use App\Models\User;
use App\Services\Extraction\AmountGrouping;
use App\Services\Extraction\ReviewCandidateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('accepts grouping as both conventions actually write it', function (): void {
    // Indian repeats twos before a final three, and the leading group runs to
    // three digits in these documents: 196,86,36,343 is on page 1014 and is read
    // correctly. An earlier, stricter rule called it suspect, which is why it is
    // pinned here.
    $grouping = app(AmountGrouping::class);

    expect($grouping->groupsCorrectly('১৯৬,৮৬,৩৬,৩৪৩'))->toBeTrue()
        ->and($grouping->groupsCorrectly('১,২৩,৪৫৬'))->toBeTrue()
        ->and($grouping->groupsCorrectly('1,234,567'))->toBeTrue()
        ->and($grouping->groupsCorrectly('১২,৩৪৫.৬৭'))->toBeTrue()
        ->and($grouping->groupsCorrectly('(১২,৩৪৫.৬৭)'))->toBeTrue()
        ->and($grouping->groupsCorrectly('৫৭৩২০২'))->toBeTrue();
});

it('suspects a comma that groups thousands the way no convention does', function (): void {
    // Page 1014 shows ৪০০.০০ and the recognizer produced "800,00". A final group
    // of two digits is where a misread decimal point shows up.
    $grouping = app(AmountGrouping::class);

    expect($grouping->groupsCorrectly('800,00'))->toBeFalse()
        ->and($grouping->groupsCorrectly('৩,৮৫'))->toBeFalse()
        ->and($grouping->groupsCorrectly('১২,০২,৩৩'))->toBeFalse();
});

it('records no normalised number for a figure whose separator is in doubt', function (): void {
    // Stripping that comma turns 400.00 into 80,000. No number is better than
    // one wrong by two orders of magnitude.
    ExtractionPage::factory()->create([
        'extracted_text' => 'মোট বরাদ্দ 800,00 কোটি টাকা এই খাতে ব্যয় করা হয়েছে বলে প্রতিবেদনে উল্লেখ',
        'extraction_path' => 'ocr_primary', 'character_count' => 90,
        'script_class' => 'bn', 'confidence' => 84.0,
    ]);

    app(ReviewCandidateGenerator::class)->generate(5);

    $field = ExtractionField::query()->where('extracted_value', '800,00')->firstOrFail();

    expect($field->normalized_value)->toBeNull()
        // It is still queued: an error dropped from the sample is an error the
        // bound never sees, which makes the measured risk optimistic.
        ->and($field->decision)->toBe('pending');
});

it('still normalises a figure that groups correctly', function (): void {
    ExtractionPage::factory()->create([
        'extracted_text' => 'সর্বমোট ১,২৩,৪৫৬ টাকা বরাদ্দ দেওয়া হয়েছে এই অর্থবছরে উন্নয়ন খাতে',
        'extraction_path' => 'ocr_primary', 'character_count' => 90,
        'script_class' => 'bn', 'confidence' => 84.0,
    ]);

    app(ReviewCandidateGenerator::class)->generate(5);

    expect(ExtractionField::query()->where('extracted_value', '১,২৩,৪৫৬')->firstOrFail()->normalized_value)
        ->toBe('১২৩৪৫৬');
});

it('tells the reviewer what to check rather than correcting it', function (): void {
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $admin = User::factory()->create();
    $admin->roles()->attach($role);

    $page = ExtractionPage::factory()->create([
        'script_class' => 'bn', 'recognized_dpi' => 150,
        'recognized_words' => [['t' => '800,00', 'c' => 90.0, 'l' => 400, 'y' => 300, 'w' => 70, 'h' => 20]],
    ]);

    ExtractionField::query()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => 'amount', 'field_type' => 'string',
        'extracted_value' => '800,00', 'script_class' => 'bn',
        'publisher_group' => 'dgcivil-bangladesh', 'confidence' => 84.0,
        'nonconformity_score' => 0.16, 'decision' => 'pending',
        'evidence_page_number' => 1, 'evidence_offset_start' => 0, 'evidence_offset_end' => 6,
    ]);

    $this->actingAs($admin)->get('/admin/sources/review')
        ->assertOk()
        ->assertSee('decimal point read as a comma', false)
        // The value is shown exactly as read. Rewriting the comma would be the
        // system answering the question it is asking.
        ->assertSee('800,00');
});
