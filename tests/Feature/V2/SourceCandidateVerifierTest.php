<?php

use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\SourceCandidateVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    robotsAbsentFor('data.example');
    app()->instance(NetworkAddressResolver::class, new class implements NetworkAddressResolver
    {
        public function resolve(string $host): array
        {
            return $host === 'data.example' ? ['203.0.113.10'] : [];
        }
    });
});

it('counts documents rather than every link on the page', function (): void {
    // A navigation menu is not an archive. The first real listing probed had
    // forty-nine links and three documents, and registering on the larger
    // number would have meant crawling a site that publishes nothing.
    Http::fake(['https://data.example/*' => Http::response(
        '<a href="/page/about-us">About</a><a href="/files/report.pdf">Report</a><a href="/files/data.xlsx">Data</a>',
        200,
        ['Content-Type' => 'text/html'],
    )]);

    $result = app(SourceCandidateVerifier::class)->verify('https://data.example/reports', ['data.example']);

    expect($result['documents'])->toBe(2)
        ->and($result['links_seen'])->toBe(3);
});

it('reports which hosts the documents actually sit on', function (): void {
    // A directorate listing whose files live on another host needs that host
    // allowlisted, and finding out after registering looks like a permission
    // failure rather than a fact about the publisher.
    Http::fake(['https://data.example/*' => Http::response(
        '<a href="https://files.example/a.pdf">A</a><a href="/b.pdf">B</a>',
        200,
        ['Content-Type' => 'text/html'],
    )]);

    $result = app(SourceCandidateVerifier::class)->verify(
        'https://data.example/reports',
        ['data.example', 'files.example'],
    );

    expect($result['hosts'])->toHaveKey('data.example');
});

it('registers nothing while verifying', function (): void {
    // The point of probing is to decide whether to register. Leaving a row
    // behind for every dead host would defeat it.
    Http::fake(['https://data.example/*' => Http::response('<a href="/x.pdf">x</a>', 200, ['Content-Type' => 'text/html'])]);

    app(SourceCandidateVerifier::class)->verify('https://data.example/reports', ['data.example']);

    expect(SourceEndpoint::query()->count())->toBe(0);
});

it('names a certificate failure apart from a host that is not there', function (): void {
    // Several .gov.bd hosts serve an incomplete chain: curl repairs it by
    // fetching the missing intermediate and OpenSSL does not, so the site looks
    // healthy in a browser and unreachable here. The remedy is a CA bundle, not
    // a firewall rule, and the two must not read alike.
    Http::fake(fn () => throw new ConnectionException('cURL error 60: SSL certificate problem: unable to get local issuer certificate'));

    $result = app(SourceCandidateVerifier::class)->verify('https://data.example/reports', ['data.example']);

    expect($result['reachable'])->toBeFalse()
        ->and($result['fault'])->toBe('tls-chain-unverified');
});
