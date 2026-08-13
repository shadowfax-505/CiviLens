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

it('shows the minimum a group needs before it is certified for anything', function (): void {
    candidate();

    $this->actingAs(reviewAdmin())->get('/admin/sources/review')
        ->assertOk()
        ->assertSee('19')
        ->assertSee('below this a group is certified for nothing');
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
