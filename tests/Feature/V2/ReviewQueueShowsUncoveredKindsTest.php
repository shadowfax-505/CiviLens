<?php

use App\Models\ExtractionField;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function queueField(string $kind, array $overrides): ExtractionField
{
    ['page' => $page] = publishableDocument();

    return ExtractionField::factory()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => $kind,
        'publisher_group' => 'supremecourt-bangladesh',
        'script_class' => 'en',
        'nonconformity_score' => 0.01,
        ...$overrides,
    ]);
}

it('names a kind the group has candidates for and has never judged', function (): void {
    // A group can read "reached" and still certify none of a kind, because a
    // threshold rests on the kinds it was fitted on.
    queueField('date', [
        'calibration_split' => 'calibration',
        'is_correct' => true,
        'gold_value' => 'seen',
        'gold_source' => 'reviewer',
    ]);

    queueField('case_number', ['gold_source' => null, 'is_correct' => null]);
    queueField('case_number', ['gold_source' => null, 'is_correct' => null]);

    $response = $this->actingAs(searchAdminUser())->get(route('admin.sources.review'));

    $response->assertOk()->assertSee('2 case number withheld', false);
});

it('says nothing about a kind the group has judged', function (): void {
    queueField('date', [
        'calibration_split' => 'calibration',
        'is_correct' => true,
        'gold_value' => 'seen',
        'gold_source' => 'reviewer',
    ]);

    queueField('date', ['gold_source' => null, 'is_correct' => null]);

    $this->actingAs(searchAdminUser())->get(route('admin.sources.review'))
        ->assertOk()
        ->assertDontSee('date withheld', false);
});
