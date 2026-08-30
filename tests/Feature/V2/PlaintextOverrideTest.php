<?php

use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Exceptions\Ingestion\UnsafeSourceUrl;
use App\Models\SourceEndpoint;
use App\Services\Extraction\LimitationsReport;
use App\Services\Ingestion\ApprovedSourceUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->instance(NetworkAddressResolver::class, new class implements NetworkAddressResolver
    {
        public function resolve(string $host): array
        {
            return $host === 'laws.example' ? ['203.0.113.30'] : [];
        }
    });
});

function lawsEndpoint(?string $plaintextReason): SourceEndpoint
{
    return SourceEndpoint::factory()->create([
        'base_url' => 'http://laws.example/acts.html',
        'allowed_hosts' => ['laws.example'],
        'allowed_path_prefixes' => ['/'],
        'plaintext_override_reason' => $plaintextReason,
    ]);
}

it('refuses plaintext for an endpoint with no recorded reason', function (): void {
    // The default has to stay refusal. This project's value is that a published
    // figure traces to bytes the publisher served, and over HTTP those bytes can
    // be altered in transit — a checksum of altered bytes attests to the
    // alteration.
    expect(fn () => app(ApprovedSourceUrlGuard::class)->validate('http://laws.example/acts.html', lawsEndpoint(null)))
        ->toThrow(UnsafeSourceUrl::class, 'Only HTTPS source URLs are allowed.');
});

it('refuses plaintext when the reason is only whitespace', function (): void {
    // An empty string in the column would otherwise buy the exception without
    // anyone having written down why.
    expect(fn () => app(ApprovedSourceUrlGuard::class)->validate('http://laws.example/acts.html', lawsEndpoint("   \n")))
        ->toThrow(UnsafeSourceUrl::class);
});

it('allows plaintext for the one endpoint that records a reason', function (): void {
    // bdlaws.minlaw.gov.bd answers 200 over HTTP and offers no HTTPS listener
    // at all. Without this the statutes every judgment cites are unreachable.
    $validated = app(ApprovedSourceUrlGuard::class)->validate(
        'http://laws.example/acts.html',
        lawsEndpoint('Operator decision: no HTTPS listener exists on this host.'),
    );

    expect($validated->url)->toBe('http://laws.example/acts.html');
});

it('still refuses a non-standard port over plaintext', function (): void {
    // The exception is about the scheme. Everything else the guard checks —
    // host allowlist, address pinning, credentials, ports — still applies.
    expect(fn () => app(ApprovedSourceUrlGuard::class)->validate(
        'http://laws.example:8080/acts.html',
        lawsEndpoint('Operator decision: no HTTPS listener exists on this host.'),
    ))->toThrow(UnsafeSourceUrl::class);
});

it('still refuses a host that is not allowlisted, reason or no reason', function (): void {
    expect(fn () => app(ApprovedSourceUrlGuard::class)->validate(
        'http://elsewhere.example/acts.html',
        lawsEndpoint('Operator decision: no HTTPS listener exists on this host.'),
    ))->toThrow(UnsafeSourceUrl::class);
});

it('keeps allowing https where the exception is present', function (): void {
    // The override widens what is accepted; it must not narrow it.
    $endpoint = lawsEndpoint('Operator decision: no HTTPS listener exists on this host.');

    $validated = app(ApprovedSourceUrlGuard::class)->validate('https://laws.example/acts.html', $endpoint);

    expect($validated->url)->toBe('https://laws.example/acts.html');
});

it('reports the provenance cost of every plaintext endpoint', function (): void {
    // An exception nobody can see is an exception nobody weighed. The report
    // names the endpoint, quotes the recorded reason, and counts what was
    // fetched that way.
    lawsEndpoint('Operator decision: no HTTPS listener exists on this host.');

    $report = collect(app(LimitationsReport::class)->build())
        ->firstWhere('limitation', 'Some sources are fetched over plaintext HTTP, so their checksums attest to what we received rather than to what the publisher served.');

    expect($report)->not->toBeNull()
        ->and($report['evidence']['endpoints'])->toHaveCount(1)
        ->and($report['evidence']['endpoints'][0]['reason'])->toContain('no HTTPS listener');
});

it('says nothing about plaintext when every source serves TLS', function (): void {
    // The report withdraws a limitation the corpus does not have, or every
    // limitation becomes furniture a reader learns to skip.
    SourceEndpoint::factory()->create(['plaintext_override_reason' => null]);

    $limitations = array_column(app(LimitationsReport::class)->build(), 'limitation');

    expect($limitations)->not->toContain('Some sources are fetched over plaintext HTTP, so their checksums attest to what we received rather than to what the publisher served.');
});
