<?php

use App\Models\ExtractionField;
use App\Models\ExtractionFieldJudgement;
use App\Models\Role;
use App\Models\User;
use App\Services\Extraction\ReviewerAgreementReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reviewer(): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => config('civiclens.roles.admin')],
        ['name' => 'Administrator'],
    );

    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->attach($role);

    return $user;
}

function judged(string $first, string $second): ExtractionField
{
    $field = ExtractionField::factory()->create(['gold_source' => 'reviewer']);

    foreach ([$first, $second] as $verdict) {
        ExtractionFieldJudgement::query()->create([
            'extraction_field_id' => $field->id,
            'user_id' => reviewer()->id,
            'verdict' => $verdict,
            'value_judged' => (string) $field->extracted_value,
        ]);
    }

    return $field;
}

it('says agreement cannot be measured rather than reporting zero', function (): void {
    // A zero reads like a measurement that came out badly. This one has not
    // been taken.
    $report = app(ReviewerAgreementReport::class)->build();

    expect($report['double_judged'])->toBe(0)
        ->and($report['kappa'])->toBeNull()
        ->and($report['reason'])->toContain('judged by two people');
});

it('measures agreement between two reviewers', function (): void {
    judged('correct', 'correct');
    judged('correct', 'correct');
    judged('correct', 'incorrect');
    judged('incorrect', 'incorrect');

    $report = app(ReviewerAgreementReport::class)->build();

    expect($report['double_judged'])->toBe(4)
        ->and($report['agreement'])->toBe(0.75)
        ->and($report['disagreements'])->toBe(['correct vs incorrect' => 1]);
});

it('reports kappa rather than raw agreement alone', function (): void {
    // These judgements are heavily skewed — most values are read correctly —
    // and two people agreeing 95% of the time on a task where "correct" is the
    // answer 95% of the time have shown nothing. Kappa asks how much better
    // than chance they did, and here they did no better at all.
    judged('correct', 'incorrect');
    judged('incorrect', 'correct');
    judged('correct', 'incorrect');
    judged('incorrect', 'correct');

    expect(app(ReviewerAgreementReport::class)->build()['kappa'])->toBeLessThan(0.0);
});

it('does not report perfect agreement as no better than chance', function (): void {
    // Both reviewers gave one answer to everything, so kappa is undefined.
    // Returning 0 would say they agreed by luck.
    judged('correct', 'correct');
    judged('correct', 'correct');

    $report = app(ReviewerAgreementReport::class)->build();

    expect($report['agreement'])->toBe(1.0)
        ->and($report['kappa'])->toBeNull();
});

it('counts labels per hour within sessions, not across the calendar', function (): void {
    // Labels made on three days a fortnight apart would otherwise read as one
    // an hour.
    $base = now()->subDays(10);

    foreach (range(0, 9) as $index) {
        ExtractionField::factory()->create([
            'gold_source' => 'reviewer',
            'reviewed_at' => $base->copy()->addSeconds($index * 30),
        ]);
    }

    // A fortnight later, one more. The gap must not count as reviewing time.
    ExtractionField::factory()->create([
        'gold_source' => 'reviewer',
        'reviewed_at' => $base->copy()->addDays(14),
    ]);

    $rate = app(ReviewerAgreementReport::class)->build()['labels_per_hour'];

    expect($rate)->toBeGreaterThan(100.0);
});
