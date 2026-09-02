<?php

use App\Models\ExtractionField;
use App\Services\Extraction\LimitationsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function thinKindFields(string $kind, int $count, array $overrides): void
{
    ['page' => $page] = publishableDocument();

    foreach (range(1, $count) as $ignored) {
        ExtractionField::factory()->create([
            'extraction_run_id' => $page->extraction_run_id,
            'extraction_page_id' => $page->id,
            'field_key' => $kind,
            'publisher_group' => 'cbad-bangladesh',
            'script_class' => 'en',
            'nonconformity_score' => 0.01,
            ...$overrides,
        ]);
    }
}

function thinKindLabelled(string $kind, int $count): void
{
    thinKindFields($kind, $count, [
        'calibration_split' => 'calibration',
        'is_correct' => true,
        'gold_value' => 'seen',
        'gold_source' => 'reviewer',
    ]);
}

function thinKindCertified(string $kind, int $count): void
{
    thinKindFields($kind, $count, ['decision' => 'accepted']);
}

it('names a kind certified on fewer labels than it would need alone', function (): void {
    thinKindLabelled('amount', 40);
    thinKindLabelled('organisation', 2);
    thinKindCertified('organisation', 53);

    $found = collect(app(LimitationsReport::class)->build(0.05))
        ->firstWhere('limitation', 'Some certified values are of a kind their group has judged only a handful of times.');

    expect($found)->not->toBeNull()
        ->and($found['evidence']['labels_of_a_kind_needed_to_certify_it_alone'])->toBe(19)
        ->and($found['evidence']['certified_on_fewer'])->toBe(53)
        ->and($found['evidence']['strata'][0])->toMatchArray([
            'kind' => 'organisation',
            'accepted' => 53,
            'labelled_of_this_kind' => 2,
        ]);
});

it('says nothing when every certified kind is judged enough', function (): void {
    thinKindLabelled('amount', 40);
    thinKindCertified('amount', 53);

    $found = collect(app(LimitationsReport::class)->build(0.05))
        ->firstWhere('limitation', 'Some certified values are of a kind their group has judged only a handful of times.');

    expect($found)->toBeNull();
});
