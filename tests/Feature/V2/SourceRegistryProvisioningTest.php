<?php

use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use App\Services\Ingestion\SourceRegistryProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function provisionFixture(string $slug = 'demo'): array
{
    return app(SourceRegistryProvisioner::class)->provision(
        $slug,
        'Demo Publisher',
        'government',
        'https://data.example/',
        'Demo Authority',
        'I authorise CivicLens to fetch public notices from data.example at a rate-limited pace.',
        [[
            'name' => 'Public listing',
            'connector_type' => 'static_html',
            'base_url' => 'https://data.example/listing',
            'allowed_hosts' => ['data.example'],
            'allowed_path_prefixes' => ['/listing'],
            'access_decision' => 'operator-authorised-public-notices',
            'rate_limit_per_minute' => 6,
        ]],
    );
}

it('creates every endpoint paused so provisioning is not crawling', function (): void {
    // Recording a decision must not start one. Resuming is a separate act.
    $result = provisionFixture();

    expect($result['paused'])->toBeTrue()
        ->and(SourceEndpoint::query()->whereNull('paused_at')->count())->toBe(0)
        ->and(SourceEndpoint::query()->sole()->health_status)->toBe('pending');
});

it('stores the authorising sentence verbatim', function (): void {
    // access_decision is a 64-character label and cannot hold it. A truncated
    // authorisation is worse than none: the record exists to show exactly what
    // a person agreed to.
    provisionFixture();
    $publisher = SourcePublisher::query()->sole();

    expect($publisher->metadata['authorisation'])
        ->toBe('I authorise CivicLens to fetch public notices from data.example at a rate-limited pace.')
        ->and($publisher->metadata['authorisation_recorded_at'])->not->toBeEmpty()
        ->and($publisher->metadata['robots_txt'])->toContain('404');
});

it('scopes the endpoint to an exact host and path prefix', function (): void {
    provisionFixture();
    $endpoint = SourceEndpoint::query()->sole();

    expect($endpoint->allowed_hosts)->toBe(['data.example'])
        ->and($endpoint->allowed_path_prefixes)->toBe(['/listing'])
        ->and($endpoint->access_decision)->toBe('operator-authorised-public-notices')
        ->and($endpoint->access_reviewed_at)->not->toBeNull()
        ->and($endpoint->rate_limit_per_minute)->toBe(6);
});

it('reports whether ingestion is enabled rather than assuming it', function (): void {
    config()->set('civiclens.ingestion.enabled', false);

    expect(provisionFixture()['ingestion_enabled'])->toBeFalse();
});

it('is idempotent so re-running does not duplicate a publisher', function (): void {
    provisionFixture();
    provisionFixture();

    expect(SourcePublisher::query()->count())->toBe(1)
        ->and(SourceEndpoint::query()->count())->toBe(1);
});

it('re-pauses an endpoint when its decision is provisioned again', function (): void {
    provisionFixture();
    SourceEndpoint::query()->sole()->forceFill(['paused_at' => null])->save();

    provisionFixture();

    expect(SourceEndpoint::query()->sole()->paused_at)->not->toBeNull();
});
