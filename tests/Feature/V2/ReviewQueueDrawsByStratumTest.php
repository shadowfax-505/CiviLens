<?php

use App\Models\ExtractionField;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function stratumCandidate(string $kind, string $value): ExtractionField
{
    ['page' => $page] = publishableDocument();

    return ExtractionField::factory()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => $kind,
        'extracted_value' => $value,
        'publisher_group' => 'supremecourt-bangladesh',
        'script_class' => 'en',
        'nonconformity_score' => 0.01,
        'gold_source' => null,
        'is_correct' => null,
    ]);
}

it('draws only the chosen kind of value', function (): void {
    // Now valid, and it was not before. While a threshold was fitted per
    // publisher and script, judging one kind on purpose enriched the group's
    // calibration set in that kind. The stratum is publisher x script x kind,
    // so a draw inside one kind is uniform inside the very population that
    // kind's threshold is applied to.
    stratumCandidate('case_number', 'Writ Petition No. 8951 of 2025');

    foreach (range(1, 40) as $index) {
        stratumCandidate('date', '0'.($index % 9 + 1).'.01.2025');
    }

    $response = $this->actingAs(searchAdminUser())->get(route('admin.sources.review', [
        'group' => 'supremecourt-bangladesh|en|case_number',
    ]));

    $response->assertOk()->assertSee('Writ Petition No. 8951 of 2025', false);
});

it('offers each kind as its own stratum to judge', function (): void {
    stratumCandidate('case_number', 'Writ Petition No. 1 of 2025');
    stratumCandidate('date', '01.01.2025');

    $this->actingAs(searchAdminUser())
        ->get(route('admin.sources.review'))
        ->assertOk()
        ->assertSee('supremecourt-bangladesh|en|case_number', false)
        ->assertSee('supremecourt-bangladesh|en|date', false);
});

it('still means every kind when a key from an older session has two parts', function (): void {
    stratumCandidate('case_number', 'Writ Petition No. 2 of 2025');

    $this->actingAs(searchAdminUser())
        ->get(route('admin.sources.review', ['group' => 'supremecourt-bangladesh|en']))
        ->assertOk()
        ->assertSee('Writ Petition No. 2 of 2025', false);
});
