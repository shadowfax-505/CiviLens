<?php

use App\Models\DiscoveredResource;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\PublicationDocument;
use App\Models\SourceArtifactVersion;
use App\Models\SourceEndpoint;
use App\Services\Extraction\LimitationsReport;
use App\Services\Publication\PublicationSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The same file, acquired from two addresses.
 *
 * @return array{first: SourceArtifactVersion, second: SourceArtifactVersion}
 */
function sameFileTwice(): array
{
    $endpoint = SourceEndpoint::factory()->create();
    $checksum = str_repeat('a', 64);

    $made = [];

    foreach (['https://example.test/a.pdf', 'https://example.test/mirror/a.pdf'] as $url) {
        $resource = DiscoveredResource::factory()->create([
            'source_endpoint_id' => $endpoint->id,
            'canonical_url' => $url,
        ]);

        $artifact = SourceArtifactVersion::factory()->create([
            'discovered_resource_id' => $resource->id,
            'sha256' => $checksum,
            'media_type' => 'application/pdf',
            'is_quarantined' => false,
        ]);

        ExtractionRun::factory()->create([
            'source_artifact_version_id' => $artifact->id,
            'page_count' => 1,
        ]);

        $made[] = $artifact;
    }

    return ['first' => $made[0], 'second' => $made[1]];
}

/** A reviewable candidate hanging off one artifact's run. */
function fieldOnArtifact(SourceArtifactVersion $artifact): ExtractionField
{
    $run = ExtractionRun::query()->where('source_artifact_version_id', $artifact->id)->firstOrFail();

    $page = ExtractionPage::factory()->create([
        'extraction_run_id' => $run->id,
        'page_number' => 1,
    ]);

    return ExtractionField::factory()->create([
        'extraction_run_id' => $run->id,
        'extraction_page_id' => $page->id,
        'field_type' => 'amount',
        'is_correct' => null,
        'gold_source' => null,
        'publisher_group' => 'dgcivil-bangladesh',
        'script_class' => 'bn',
    ]);
}

it('treats the earliest acquisition of a file as the one that counts', function (): void {
    ['first' => $first, 'second' => $second] = sameFileTwice();

    expect(SourceArtifactVersion::query()->canonical()->pluck('id')->all())->toContain($first->id)
        ->not->toContain($second->id)
        ->and(SourceArtifactVersion::query()->duplicate()->pluck('id')->all())->toBe([$second->id]);
});

it('publishes a document held twice only once', function (): void {
    // A corpus that lists the same document twice overstates itself in the one
    // number a reader is most likely to quote.
    sameFileTwice();

    $builder = app(PublicationSnapshotBuilder::class);
    $snapshot = $builder->publish($builder->build('published'));

    expect(PublicationDocument::query()->where('publication_snapshot_id', $snapshot->id)->count())->toBe(1);
});

it('never offers a reviewer a value from a duplicate copy', function (): void {
    // Uniform-at-random is not enough on its own. A judgement made on a
    // duplicate enters calibration as though it were an independent document,
    // and the bound is then arithmetic about a population that does not exist.
    ['first' => $first, 'second' => $second] = sameFileTwice();

    $keep = fieldOnArtifact($first);
    $drop = fieldOnArtifact($second);

    $this->actingAs(searchAdminUser());

    $seen = [];

    for ($attempt = 0; $attempt < 25; $attempt++) {
        $response = $this->get(route('admin.sources.review'));
        $response->assertOk();
        $item = $response->viewData('item');

        if ($item !== null) {
            $seen[$item->id] = true;
        }
    }

    expect($seen)->toHaveKey($keep->id)
        ->and($seen)->not->toHaveKey($drop->id);
});

it('says the corpus is smaller than the artifact count, with the numbers', function (): void {
    // Filtering duplicates out silently would make the corpus look like it was
    // always counted this way. What changed has to be visible.
    sameFileTwice();

    $report = collect(app(LimitationsReport::class)->build())
        ->firstWhere('limitation', 'The archive holds some files twice, and the corpus is smaller than the artifact count.');

    expect($report)->not->toBeNull()
        ->and($report['evidence']['artifacts'])->toBe(2)
        ->and($report['evidence']['distinct_files'])->toBe(1)
        ->and($report['evidence']['second_copies'])->toBe(1);
});

it('withdraws that once no file is held twice', function (): void {
    publishableDocument();

    $limitations = array_column(app(LimitationsReport::class)->build(), 'limitation');

    expect($limitations)
        ->not->toContain('The archive holds some files twice, and the corpus is smaller than the artifact count.');
});
