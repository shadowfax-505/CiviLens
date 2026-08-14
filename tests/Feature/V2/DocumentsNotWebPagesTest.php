<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\SourceArtifactVersion;
use App\Services\Extraction\ReviewCandidateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pageFromArtifact(string $mediaType, string $text): ExtractionPage
{
    $artifact = SourceArtifactVersion::factory()->create(['media_type' => $mediaType]);
    $run = ExtractionRun::factory()->create(['source_artifact_version_id' => $artifact->id]);

    return ExtractionPage::factory()->create([
        'extraction_run_id' => $run->id,
        'extracted_text' => $text,
        'extraction_path' => 'native',
        'character_count' => mb_strlen($text),
        'script_class' => 'en',
        'confidence' => 90.0,
    ]);
}

it('does not queue website furniture for adjudication', function (): void {
    // Discovery follows every link a listing carries, so acquisition holds the
    // publisher's own menus: 58 of one publisher's 59 acquisitions were its
    // sector pages and contact forms. Their text extracts perfectly well, and a
    // reviewer asked to judge it is being asked to certify a navigation bar.
    pageFromArtifact(
        'text/html',
        'HOME | LINK | CONTACT US | SITEMAP Department of Printing and Publications 1,25,000.50 visitors since 2019',
    );

    $summary = app(ReviewCandidateGenerator::class)->generate(10);

    expect($summary['created'])->toBe(0)
        ->and(ExtractionField::query()->count())->toBe(0);
});

it('still queues values from a document', function (): void {
    pageFromArtifact(
        'application/pdf',
        'Total allocation for the financial year was 1,25,000.50 crore taka across every ministry listed here.',
    );

    $summary = app(ReviewCandidateGenerator::class)->generate(10);

    expect($summary['created'])->toBeGreaterThan(0)
        ->and(ExtractionField::query()->where('extracted_value', '1,25,000.50')->exists())->toBeTrue();
});

it('leaves an archived record feed out of the queue', function (): void {
    // API pages are archived for provenance, not read for figures. Nothing in
    // them was recognized, so there is nothing to judge as correctly read.
    pageFromArtifact('application/json', '{"projects":[{"totalamt":"1,25,000.50","id":"P123456"}]}');

    expect(app(ReviewCandidateGenerator::class)->generate(10)['created'])->toBe(0);
});
