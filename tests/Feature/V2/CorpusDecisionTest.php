<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\Role;
use App\Models\User;
use App\Services\Extraction\FieldDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @param  array{score: float, correct?: bool|null, split?: string|null, group?: string}  $attributes */
function decidable(array $attributes): ExtractionField
{
    $page = ExtractionPage::factory()->create(['script_class' => 'bn']);

    return ExtractionField::query()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => 'amount', 'field_type' => 'string',
        'extracted_value' => '1,25,000.50', 'script_class' => 'bn',
        'publisher_group' => $attributes['group'] ?? 'dgcivil-bangladesh',
        'confidence' => 84.0,
        'nonconformity_score' => $attributes['score'],
        'decision' => 'pending',
        'is_correct' => $attributes['correct'] ?? null,
        'gold_value' => ($attributes['correct'] ?? null) === true ? '1,25,000.50' : null,
        'gold_source' => array_key_exists('correct', $attributes) ? 'reviewer' : null,
        'calibration_split' => $attributes['split'] ?? null,
        'evidence_page_number' => 1, 'evidence_offset_start' => 0, 'evidence_offset_end' => 11,
    ]);
}

function calibrationOf(int $count, float $score = 0.1): void
{
    for ($i = 0; $i < $count; $i++) {
        decidable(['score' => $score, 'correct' => true, 'split' => 'calibration']);
    }
}

it('decides the corpus, not only the held-out sample', function (): void {
    // The service only ever looked at calibration_split = test, so 4,252
    // extracted values sat pending forever: the pipeline stopped one step
    // before it did anything.
    calibrationOf(25);
    $production = decidable(['score' => 0.05]);

    $counts = app(FieldDecisionService::class)->decide(0.05);

    expect($counts[FieldDecisionService::ACCEPTED])->toBe(1)
        ->and($production->refresh()->decision)->toBe(FieldDecisionService::ACCEPTED);
});

it('never decides a field a reviewer has judged', function (): void {
    // Those are the evidence the threshold is computed from. Writing a decision
    // onto them would fold the calibration set into what it certifies.
    calibrationOf(25);
    $judged = decidable(['score' => 0.05, 'correct' => true, 'split' => 'test']);

    app(FieldDecisionService::class)->decide(0.05);

    expect($judged->refresh()->decision)->toBe('pending');
});

it('records the level an acceptance was certified at, not the level asked for', function (): void {
    // Nine labels cannot support 0.05. The group is certified at 0.10 and an
    // acceptance in it claims 0.10, which is a weaker statement and has to
    // travel with the field.
    calibrationOf(9);
    $production = decidable(['score' => 0.05]);

    app(FieldDecisionService::class)->decide(0.05);
    $production->refresh();

    expect($production->decision)->toBe(FieldDecisionService::ACCEPTED)
        ->and((float) $production->decision_alpha)->toBe(0.1)
        ->and($production->decision_basis)->toBe('group');
});

it('defers everything in a group with no calibration at all', function (): void {
    calibrationOf(25);
    $orphan = decidable(['score' => 0.01, 'group' => 'never-seen-publisher']);

    app(FieldDecisionService::class)->decide(0.05);

    expect($orphan->refresh()->decision)->toBe(FieldDecisionService::DEFERRED)
        // A deferral claims nothing, so it qualifies nothing.
        ->and($orphan->decision_basis)->toBeNull();
});

it('defers a field carrying no score', function (): void {
    calibrationOf(25);
    $unscored = decidable(['score' => 0.05]);
    $unscored->forceFill(['nonconformity_score' => null])->save();

    app(FieldDecisionService::class)->decide(0.05);

    expect($unscored->refresh()->decision)->toBe(FieldDecisionService::DEFERRED);
});

it('shows on screen what the certification decided', function (): void {
    // Thresholds were computed for weeks and nothing applied them; no screen
    // said so, because no screen reported decisions at all.
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $admin = User::factory()->create();
    $admin->roles()->attach($role);

    calibrationOf(25);
    decidable(['score' => 0.05]);
    decidable(['score' => 0.99]);

    app(FieldDecisionService::class)->decide(0.05);

    test()->actingAs($admin)->get('/admin/sources/extraction')
        ->assertOk()
        ->assertSee('Auto-accepted')
        ->assertSee('Deferred')
        // Borrowed acceptances are counted apart: they certify a wider
        // population, not the group the field is in.
        ->assertSee('certified for a wider population, not for this group');
});
