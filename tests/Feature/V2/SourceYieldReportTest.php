<?php

use App\Models\DiscoveredResource;
use App\Models\SourceArtifactVersion;
use App\Models\SourceEndpoint;
use App\Models\TenderObservation;
use App\Services\Ingestion\SourceYieldReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('says a source produced nothing and nobody has looked', function (): void {
    // An endpoint yielding nothing looks identical to one nobody ran: both are
    // a zero. ADR-020 keeps CAG registered and empty as a recorded decision,
    // and that only means something if an unexplained zero is distinguishable
    // from it.
    SourceEndpoint::factory()->create(['last_crawled_at' => null]);

    $report = app(SourceYieldReport::class)->build();

    expect($report['endpoints'][0]['verdict'])->toBe('Never crawled.')
        ->and($report['unexplained'])->toHaveCount(1);
});

it('counts records from a listing that publishes rows rather than files', function (): void {
    // e-GP answers with listing rows, and counting only artifacts reported it
    // as an endpoint that produced nothing and should be looked at — 3,468
    // captured notices misread as a breakage.
    $endpoint = SourceEndpoint::factory()->create(['last_crawled_at' => now()]);
    $resource = DiscoveredResource::factory()->create(['source_endpoint_id' => $endpoint->id]);

    foreach (range(1, 3) as $index) {
        TenderObservation::query()->create([
            'source_publisher_id' => $endpoint->source_publisher_id,
            'discovered_resource_id' => $resource->id,
            'external_id' => (string) $index,
            'observation_hash' => hash('sha256', (string) $index),
            'observed_at' => now(),
        ]);
    }

    $report = app(SourceYieldReport::class)->build();

    expect($report['endpoints'][0]['observations'])->toBe(3)
        ->and($report['endpoints'][0]['verdict'])->toContain('records captured from a listing')
        ->and($report['unexplained'])->toBeEmpty();
});

it('separates documents from archived web pages', function (): void {
    // 476 artifacts is not 476 documents, and the difference is what a claim
    // about extraction rests on.
    $endpoint = SourceEndpoint::factory()->create(['last_crawled_at' => now()]);
    $resource = DiscoveredResource::factory()->create(['source_endpoint_id' => $endpoint->id]);

    SourceArtifactVersion::factory()->create([
        'discovered_resource_id' => $resource->id,
        'version_number' => 1,
        'media_type' => 'application/pdf',
    ]);

    foreach (range(2, 5) as $version) {
        SourceArtifactVersion::factory()->create([
            'discovered_resource_id' => $resource->id,
            'version_number' => $version,
            'media_type' => 'text/html',
        ]);
    }

    $row = app(SourceYieldReport::class)->build()['endpoints'][0];

    expect($row['documents'])->toBe(1)
        ->and($row['artifacts_archived'])->toBe(5)
        ->and($row['verdict'])->toBe('1 documents.');
});

it('names what the publisher actually said when nothing was archived', function (): void {
    // A zero beside "HTTP 404 eleven times" is a dead route. A zero beside
    // nothing is a source nobody crawled, and the two need different work.
    $endpoint = SourceEndpoint::factory()->create(['last_crawled_at' => now()]);
    $resource = DiscoveredResource::factory()->create(['source_endpoint_id' => $endpoint->id]);

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'ingestion',
        'payload' => json_encode([
            'displayName' => 'App\Jobs\FetchDiscoveredResourceArtifact',
            'data' => ['command' => 'O:8:"stdClass":resourceId";i:'.$resource->id.';'],
        ]),
        'exception' => "App\Exceptions\Ingestion\AcquisitionFailed: Source returned HTTP 404.\n#0 ...",
        'failed_at' => now(),
    ]);

    $row = app(SourceYieldReport::class)->build()['endpoints'][0];

    expect($row['failures'])->toBe(['HTTP 404' => 1])
        ->and($row['verdict'])->toContain('answered HTTP 404 1 times')
        ->and(app(SourceYieldReport::class)->build()['unexplained'])->toBeEmpty();
});

it('says when a document was acquired that nothing can read', function (): void {
    // 13 Word documents from IMED sit here. Counting them as documents
    // overstates what this corpus can show: a document nothing reads yields no
    // page, no figure and no evidence, and the pipeline reads a document by
    // rendering its pages and looking at them.
    $endpoint = SourceEndpoint::factory()->create(['last_crawled_at' => now()]);
    $resource = DiscoveredResource::factory()->create(['source_endpoint_id' => $endpoint->id]);

    SourceArtifactVersion::factory()->create([
        'discovered_resource_id' => $resource->id,
        'version_number' => 1,
        'media_type' => 'application/pdf',
    ]);

    SourceArtifactVersion::factory()->create([
        'discovered_resource_id' => $resource->id,
        'version_number' => 2,
        'media_type' => 'application/msword',
    ]);

    $row = app(SourceYieldReport::class)->build()['endpoints'][0];

    expect($row['documents'])->toBe(2)
        ->and($row['documents_no_extractor_can_read'])->toBe(1)
        ->and($row['verdict'])->toContain('1 are in a format nothing here can read');
});
