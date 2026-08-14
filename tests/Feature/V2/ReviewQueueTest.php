<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\Role;
use App\Models\User;
use App\Services\Extraction\ReviewCandidateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reviewAdmin(): User
{
    // firstOrCreate, because a test that adjudicates twice calls this twice.
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $admin = User::factory()->create();
    $admin->roles()->attach($role);

    return $admin;
}

function candidate(array $overrides = []): ExtractionField
{
    $run = ExtractionRun::factory()->create();
    $page = ExtractionPage::factory()->create([
        'extraction_run_id' => $run->id,
        'extracted_text' => 'ক্রমিক 1,25,000.50 তারিখ 12.08.2026',
    ]);

    return ExtractionField::query()->create(array_merge([
        'extraction_run_id' => $run->id,
        'extraction_page_id' => $page->id,
        'field_key' => 'amount',
        'field_type' => 'string',
        'extracted_value' => '1,25,000.50',
        'script_class' => 'bn',
        'publisher_group' => 'dgcivil-bangladesh',
        'confidence' => 84.0,
        'nonconformity_score' => 0.16,
        'decision' => 'pending',
        'evidence_page_number' => 1,
        'evidence_offset_start' => 7,
        'evidence_offset_end' => 18,
    ], $overrides));
}

it('creates candidates with no gold value and no outcome', function (): void {
    // A generator that decided correctness would be marking its own homework,
    // and the guarantee computed from it would be vacuous.
    $run = ExtractionRun::factory()->create();
    ExtractionPage::factory()->create([
        'extraction_run_id' => $run->id,
        'extraction_path' => 'ocr_primary',
        'character_count' => 400,
        'extracted_text' => str_repeat('ক্রমিক 1,25,000.50 তারিখ 12.08.2026 সূত্র 44.07.9000.159 ', 4),
    ]);

    $summary = app(ReviewCandidateGenerator::class)->generate(10);

    expect($summary['created'])->toBeGreaterThan(0)
        ->and(ExtractionField::query()->whereNotNull('gold_value')->count())->toBe(0)
        ->and(ExtractionField::query()->whereNotNull('is_correct')->count())->toBe(0)
        ->and(ExtractionField::query()->calibratable()->count())->toBe(0);
});

it('does not queue a page the recognizer would not vouch for', function (): void {
    // Adjudicating abstained text spends the reviewer on text the system has
    // already declined to stand behind.
    $run = ExtractionRun::factory()->create();
    ExtractionPage::factory()->create([
        'extraction_run_id' => $run->id,
        'extraction_path' => 'abstained',
        'character_count' => 400,
        'extracted_text' => str_repeat('1,25,000.50 12.08.2026 ', 6),
    ]);

    expect(app(ReviewCandidateGenerator::class)->generate(10)['created'])->toBe(0);
});

it('records a verdict as the reviewer label the bound is computed from', function (): void {
    $field = candidate();

    $this->actingAs(reviewAdmin())
        ->post(route('admin.sources.review.store', $field), ['verdict' => 'correct'])
        ->assertRedirect();

    $field->refresh();

    expect($field->is_correct)->toBeTrue()
        ->and($field->gold_source)->toBe('reviewer')
        ->and($field->gold_value)->toBe('1,25,000.50')
        ->and($field->calibration_split)->toBeIn(['calibration', 'test'])
        ->and(ExtractionField::query()->calibratable()->count())->toBe(1);
});

it('invents no gold value when a reading is rejected', function (): void {
    // What the value should have said is a separate question this screen does
    // not ask, and guessing it would put fiction in the gold column.
    $field = candidate();

    $this->actingAs(reviewAdmin())
        ->post(route('admin.sources.review.store', $field), ['verdict' => 'incorrect']);

    $field->refresh();

    expect($field->is_correct)->toBeFalse()
        ->and($field->gold_value)->toBeNull()
        ->and($field->gold_source)->toBe('reviewer');
});

it('keeps an unsure item out of the calibration set', function (): void {
    // A coerced label would be treated downstream as though someone had known.
    $field = candidate();

    $this->actingAs(reviewAdmin())
        ->post(route('admin.sources.review.store', $field), ['verdict' => 'unsure']);

    $field->refresh();

    expect($field->is_correct)->toBeNull()
        ->and($field->gold_source)->toBe('reviewer-unsure')
        ->and(ExtractionField::query()->calibratable()->count())->toBe(0);
});

it('does not offer the same item twice', function (): void {
    $field = candidate();

    $this->actingAs(reviewAdmin())->post(route('admin.sources.review.store', $field), ['verdict' => 'unsure']);

    $this->actingAs(reviewAdmin())->get('/admin/sources/review')
        ->assertOk()
        ->assertSee('Nothing waiting');
});

it('shows what a group needs for the target level without calling a rare group hopeless', function (): void {
    // The screen used to say a group below nineteen was certified for nothing,
    // which describes the target rather than the group: n >= 1/alpha - 1
    // rearranges to alpha >= 1/(n+1), so nine labels certify at 0.10.
    candidate();

    $this->actingAs(reviewAdmin())->get('/admin/sources/review')
        ->assertOk()
        ->assertSee('19')
        ->assertSee('a smaller group certifies at a looser level, not at none');
});

it('keeps the queue behind the registry policy', function (): void {
    $field = candidate();

    $this->actingAs(User::factory()->create())->get('/admin/sources/review')->assertForbidden();
    $this->actingAs(User::factory()->create())
        ->post(route('admin.sources.review.store', $field), ['verdict' => 'correct'])
        ->assertForbidden();
});

it('never queues a page whose text layer is mis-encoded', function (): void {
    // Legacy Bengali fonts mapped as Unicode produce dense, plausible-looking
    // text that decodes to the wrong characters. 88% of the first candidate
    // batch came from such pages: a reviewer would have marked nine in ten
    // incorrect and the bound would have measured the encoding bug.
    $run = ExtractionRun::factory()->create();
    ExtractionPage::factory()->create([
        'extraction_run_id' => $run->id,
        'extraction_path' => 'native',
        'character_count' => 400,
        // Vowel signs detached from consonants, as a mis-mapped font produces.
        'extracted_text' => str_repeat('স ূ ড ি ত ্ র ে া ি ু 1,25,000.50 12.08.2026 ', 20),
    ]);

    expect(app(ReviewCandidateGenerator::class)->generate(10)['created'])->toBe(0);
});

it('shows the page a value came from, not just the text around it', function (): void {
    // Without the page, the screen asks a reviewer to compare OCR output
    // against OCR output. It agrees with itself, so the only honest answer is
    // "can't tell" every time — which is what happened on the first attempt.
    // The page alone was not enough either: on a budget table of hundreds of
    // figures, finding the value became the reviewer's job, so the characters
    // are marked and shown close up.
    $field = candidate();

    $this->actingAs(reviewAdmin())->get('/admin/sources/review')
        ->assertOk()
        ->assertSee('The whole page')
        ->assertSee('marked on the page and')
        ->assertSee(route('admin.sources.review.page', $field), false);
});

it('asks only whether the characters match', function (): void {
    // Three different questions were being read into one verdict: do the
    // characters match, is the number sensible, is the category right. Only the
    // first is answerable from the page, and only the first is asked.
    candidate();

    $this->actingAs(reviewAdmin())->get('/admin/sources/review')
        ->assertOk()
        ->assertSee('Do these characters match the page?')
        ->assertSee('not whether the number is')
        ->assertSee('Matches the page')
        ->assertSee("Can't tell", false);
});

it('does not queue a bare year as an amount', function (): void {
    // "2016" was queued as an Amount, leaving a reviewer to decide whether a
    // correctly read year is a correctly read amount.
    $tokens = app(ReviewCandidateGenerator::class)->tokens('বছর 2016 কোড 4111 টাকা 1,25,000.50');
    $values = array_column($tokens, 'value');

    expect($values)->toContain('1,25,000.50')
        ->and($values)->not->toContain('2016')
        ->and($values)->not->toContain('4111');
});

it('does not queue a fragment of a mangled figure', function (): void {
    // "US$23,¢8,80b" yielded the token "23,". Asking whether that matches the
    // page has no useful answer: the characters are on the page, but the value
    // is not a value, and a reviewer has nothing to decide.
    $values = array_column(app(ReviewCandidateGenerator::class)->tokens('US$23,¢8,80b এবং ১৪, ও ৫৭,১৩,৮৪,১০২'), 'value');

    expect($values)->toContain('৫৭,১৩,৮৪,১০২')
        ->and($values)->not->toContain('23,')
        ->and($values)->not->toContain('১৪,');
});

it('tells the reviewer that a lost table column is expected', function (): void {
    // Scanned tables lose their columns when recognized, so a figure often
    // arrives without the row it belonged to. That is a limitation of the data,
    // not a wrong reading, and a reviewer who is not told will hesitate over
    // every table figure.
    candidate();

    $this->actingAs(reviewAdmin())->get('/admin/sources/review')
        ->assertOk()
        ->assertSee('lose their columns when they are recognized', false)
        ->assertSee('does not make the reading wrong');
});
