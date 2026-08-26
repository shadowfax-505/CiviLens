<?php

use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Models\DiscoveredResource;
use App\Models\SourceArtifactVersion;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\ArchivedPageLinkHarvester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');

    app()->instance(NetworkAddressResolver::class, new class implements NetworkAddressResolver
    {
        public function resolve(string $host): array
        {
            return in_array($host, ['data.example', 'storage.example'], true) ? ['203.0.113.10'] : [];
        }
    });
});

function archivedPage(string $html, array $endpointOverrides = []): SourceEndpoint
{
    $endpoint = SourceEndpoint::factory()->create(array_merge([
        'base_url' => 'https://data.example/reports/',
        'allowed_hosts' => ['data.example'],
        'allowed_path_prefixes' => ['/reports'],
    ], $endpointOverrides));

    $resource = DiscoveredResource::factory()->create([
        'source_endpoint_id' => $endpoint->id,
        'canonical_url' => 'https://data.example/reports/index.html',
    ]);

    Storage::disk('local')->put('archived/page.html', $html);

    SourceArtifactVersion::factory()->create([
        'discovered_resource_id' => $resource->id,
        'media_type' => 'text/html',
        'storage_disk' => 'local',
        'storage_path' => 'archived/page.html',
        'is_quarantined' => false,
    ]);

    return $endpoint;
}

it('finds a document linked from a page already archived', function (): void {
    // Discovery reads an endpoint's listing and stops. On this corpus that left
    // documents unfetched inside pages already held — including CAG's storage,
    // reached from cbad's pages, which ADR-020 records CAG as not indexing.
    archivedPage('<a href="/reports/audit-2019.pdf">Audit</a>');

    $result = app(ArchivedPageLinkHarvester::class)->harvest(apply: true);

    expect($result['recorded'])->toBe(1)
        ->and(DiscoveredResource::query()->where('canonical_url', 'https://data.example/reports/audit-2019.pdf')->exists())->toBeTrue();
});

it('refuses a link outside the endpoint allowlist', function (): void {
    // A page can link anywhere. Following one off the allowlist would make the
    // archive the crawler's instruction set.
    archivedPage('<a href="https://elsewhere.example/reports/x.pdf">Elsewhere</a>');

    $result = app(ArchivedPageLinkHarvester::class)->harvest(apply: true);

    expect($result['refused'])->toBe(1)
        ->and($result['recorded'])->toBe(0);
});

it('refuses a link outside the endpoint path prefixes', function (): void {
    archivedPage('<a href="/private/secret.pdf">Not ours</a>');

    expect(app(ArchivedPageLinkHarvester::class)->harvest(apply: true)['refused'])->toBe(1);
});

it('resolves a relative link against the page it was found on', function (): void {
    archivedPage('<a href="audit-2020.pdf">Audit</a>');

    app(ArchivedPageLinkHarvester::class)->harvest(apply: true);

    expect(DiscoveredResource::query()->where('canonical_url', 'https://data.example/reports/audit-2020.pdf')->exists())->toBeTrue();
});

it('does not propose a document already discovered', function (): void {
    $endpoint = archivedPage('<a href="/reports/known.pdf">Known</a>');

    DiscoveredResource::factory()->create([
        'source_endpoint_id' => $endpoint->id,
        'canonical_url' => 'https://data.example/reports/known.pdf',
    ]);

    $result = app(ArchivedPageLinkHarvester::class)->harvest(apply: true);

    expect($result['already_known'])->toBe(1)
        ->and($result['recorded'])->toBe(0);
});

it('records without fetching', function (): void {
    // Acquisition, with its address pinning, byte ceilings and malware
    // scanning, stays the only thing that fetches. This proposes; it does not
    // reach out.
    archivedPage('<a href="/reports/audit.pdf">Audit</a>');

    app(ArchivedPageLinkHarvester::class)->harvest(apply: true);

    $resource = DiscoveredResource::query()->where('canonical_url', 'https://data.example/reports/audit.pdf')->firstOrFail();

    expect($resource->status)->toBe('queued')
        ->and(SourceArtifactVersion::query()->where('discovered_resource_id', $resource->id)->exists())->toBeFalse()
        // Traceable back to the page a person can read.
        ->and($resource->discovery_url)->toBe('https://data.example/reports/index.html');
});

it('changes nothing without apply', function (): void {
    archivedPage('<a href="/reports/audit.pdf">Audit</a>');

    $result = app(ArchivedPageLinkHarvester::class)->harvest(apply: false);

    expect($result['recorded'])->toBe(1)
        ->and(DiscoveredResource::query()->where('canonical_url', 'like', '%audit.pdf')->exists())->toBeFalse();
});
