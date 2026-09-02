<?php

use App\Models\ExtractionField;
use App\Services\Extraction\LimitationsReport;
use App\Services\Extraction\PaperReport;
use App\Services\Extraction\ReviewCandidateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function unlocatableValue(string $kind, ?string $basis = 'unlocatable'): ExtractionField
{
    ['page' => $page] = publishableDocument();

    return ExtractionField::factory()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => $kind,
        'score_basis' => $basis,
        'nonconformity_score' => $basis === 'unlocatable' ? null : 0.2,
    ]);
}

function unlocatableEvidence(): array
{
    // The report is a flat list of limitations, not a keyed map.
    $limitations = app(LimitationsReport::class)->build(0.05);

    foreach ($limitations as $entry) {
        if (is_array($entry) && str_contains((string) ($entry['limitation'] ?? ''), 'cannot be found again')) {
            return (array) $entry['evidence'];
        }
    }

    return [];
}

it('does not count cited context as a value the locator failed to find', function (): void {
    // `unlocatable` meant one thing — a value the locator could not find, which
    // is a backlog worth working down — until a kind arrived that is
    // unlocatable by construction. A finding span is a whole sentence, kept
    // past anything the locator can span on purpose so it can never certify.
    // Counting some 3,600 of those as a shortfall would make the ceiling on
    // what the corpus can certify look lower every time another judgment was
    // read correctly.
    unlocatableValue('amount');
    unlocatableValue(ReviewCandidateGenerator::CITED_CONTEXT_KIND);
    unlocatableValue(ReviewCandidateGenerator::CITED_CONTEXT_KIND);

    $evidence = unlocatableEvidence();

    expect($evidence['unlocatable'])->toBe(1)
        // Named rather than merely subtracted, so anyone checking the
        // arithmetic against the corpus can see what was left out.
        ->and($evidence['cited_context_excluded'])->toBe(2);
});

it('keeps cited context out of both sides of the share', function (): void {
    // Excluding it from the numerator alone would understate the share instead
    // of correcting it.
    unlocatableValue('amount');
    unlocatableValue('date', 'second-read');
    unlocatableValue(ReviewCandidateGenerator::CITED_CONTEXT_KIND);

    $evidence = unlocatableEvidence();

    expect($evidence['scored'])->toBe(2)
        ->and($evidence['share'])->toBe(0.5);
});

it('keeps cited context out of the paper report ceiling too', function (): void {
    unlocatableValue('amount');
    unlocatableValue(ReviewCandidateGenerator::CITED_CONTEXT_KIND);

    $report = app(PaperReport::class)->build(0.05);

    $found = null;

    array_walk_recursive($report, function (mixed $value, int|string $key) use (&$found): void {
        if ($key === 'values_not_locatable') {
            $found = $value;
        }
    });

    expect($found)->toBe(1);
});
