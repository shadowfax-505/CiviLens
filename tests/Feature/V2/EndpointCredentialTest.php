<?php

use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\SafeHttpTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->instance(NetworkAddressResolver::class, new class implements NetworkAddressResolver
    {
        public function resolve(string $host): array
        {
            return in_array($host, ['api.example', 'elsewhere.example'], true) ? ['203.0.113.10'] : [];
        }
    });

    config(['civiclens.ingestion.keys.test' => 'secret-key-value']);
});

function keyedEndpoint(array $auth = ['header' => 'X-Api-Key', 'config' => 'civiclens.ingestion.keys.test']): SourceEndpoint
{
    return SourceEndpoint::factory()->create([
        'base_url' => 'https://api.example/data/',
        'allowed_hosts' => ['api.example'],
        'allowed_path_prefixes' => ['/data'],
        'connector_options' => ['auth' => $auth],
    ]);
}

it('sends the credential a keyed endpoint needs', function (): void {
    Http::fake(['https://api.example/*' => Http::response('body', 200)]);

    app(SafeHttpTransport::class)->get('https://api.example/data/x.json', keyedEndpoint());

    Http::assertSent(fn ($request): bool => $request->header('X-Api-Key') === ['secret-key-value']);
});

it('keeps the secret out of the endpoint row', function (): void {
    // Endpoint rows are dumped in backups, shown in the admin registry and
    // readable by anyone with database access. A key stored there has
    // effectively been published, so only its config key name is stored.
    $endpoint = keyedEndpoint();

    expect(json_encode($endpoint->connectorOptions()))
        ->not->toContain('secret-key-value')
        ->and($endpoint->connectorOptions()['auth']['config'])->toBe('civiclens.ingestion.keys.test');
});

it('withholds the credential from a host outside the allowlist', function (): void {
    // A redirect off-site is exactly how a key reaches a third party.
    Http::fake([
        'https://api.example/*' => Http::response('', 302, ['Location' => 'https://elsewhere.example/data/x.json']),
        'https://elsewhere.example/*' => Http::response('body', 200),
    ]);

    $endpoint = SourceEndpoint::factory()->create([
        'base_url' => 'https://api.example/data/',
        'allowed_hosts' => ['api.example', 'elsewhere.example'],
        'allowed_path_prefixes' => ['/data'],
        'connector_options' => ['auth' => ['header' => 'X-Api-Key', 'config' => 'civiclens.ingestion.keys.test']],
    ]);

    // Both hosts are allowlisted here, so the credential travels. Narrow the
    // allowlist and it must not.
    app(SafeHttpTransport::class)->get('https://api.example/data/x.json', $endpoint);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'elsewhere.example')
        && $request->header('X-Api-Key') === ['secret-key-value']);

    Http::fake([
        'https://api.example/*' => Http::response('', 302, ['Location' => 'https://elsewhere.example/data/x.json']),
        'https://elsewhere.example/*' => Http::response('body', 200),
    ]);

    $endpoint->forceFill(['allowed_hosts' => ['api.example']])->save();

    expect(fn () => app(SafeHttpTransport::class)->get('https://api.example/data/x.json', $endpoint))
        ->toThrow(Exception::class);
});

it('sends nothing when a keyed endpoint has no key configured', function (): void {
    // The source answers 401 and says so, rather than being fetched
    // anonymously and looking like the publisher changed its mind.
    config(['civiclens.ingestion.keys.test' => null]);

    Http::fake(['https://api.example/*' => Http::response('body', 200)]);

    app(SafeHttpTransport::class)->get('https://api.example/data/x.json', keyedEndpoint());

    Http::assertSent(fn ($request): bool => $request->header('X-Api-Key') === []);
});

it('sends nothing for an endpoint that declares no credential', function (): void {
    Http::fake(['https://api.example/*' => Http::response('body', 200)]);

    $endpoint = SourceEndpoint::factory()->create([
        'base_url' => 'https://api.example/data/',
        'allowed_hosts' => ['api.example'],
        'allowed_path_prefixes' => ['/data'],
        'connector_options' => [],
    ]);

    app(SafeHttpTransport::class)->get('https://api.example/data/x.json', $endpoint);

    Http::assertSent(fn ($request): bool => $request->header('X-Api-Key') === []);
});
