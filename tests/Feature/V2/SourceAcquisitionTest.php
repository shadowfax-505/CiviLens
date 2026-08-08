<?php

use App\Contracts\Ingestion\ArtifactFetcher;
use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Data\Ingestion\AcquisitionResult;
use App\Data\Ingestion\CrawlCursor;
use App\Data\Ingestion\MalwareScanResult;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Exceptions\Ingestion\UnsafeSourceUrl;
use App\Jobs\FetchDiscoveredResourceArtifact;
use App\Models\DiscoveredResource;
use App\Models\Role;
use App\Models\SourceActivity;
use App\Models\SourceArtifactVersion;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use App\Models\User;
use App\Services\Ingestion\ApprovedSourceUrlGuard;
use App\Services\Ingestion\ArtifactMediaTypeInspector;
use App\Services\Ingestion\Connectors\ApiFeedSourceConnector;
use App\Services\Ingestion\DiscoveryDocumentParser;
use App\Services\Ingestion\SafeHttpTransport;
use App\Services\Ingestion\SourceAcquisitionService;
use App\Services\Ingestion\SourceRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function bindSourceAddresses(array $addressesByHost): void
{
    app()->instance(NetworkAddressResolver::class, new class($addressesByHost) implements NetworkAddressResolver
    {
        /** @param array<string, list<string>> $addressesByHost */
        public function __construct(private readonly array $addressesByHost) {}

        public function resolve(string $host): array
        {
            return $this->addressesByHost[$host] ?? [];
        }
    });
}

it('rejects private, non-https, credentialed, and non-allowlisted source URLs', function (): void {
    bindSourceAddresses(['data.example' => ['10.0.0.2']]);
    $endpoint = SourceEndpoint::factory()->make();
    $guard = app(ApprovedSourceUrlGuard::class);

    expect(fn () => $guard->validate('https://data.example/file.pdf', $endpoint))->toThrow(UnsafeSourceUrl::class)
        ->and(fn () => $guard->validate('http://data.example/file.pdf', $endpoint))->toThrow(UnsafeSourceUrl::class)
        ->and(fn () => $guard->validate('https://user:secret@data.example/file.pdf', $endpoint))->toThrow(UnsafeSourceUrl::class)
        ->and(fn () => $guard->validate('https://other.example/file.pdf', $endpoint))->toThrow(UnsafeSourceUrl::class);
});

it('enforces reviewed path prefixes and rejects encoded traversal', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make(['allowed_path_prefixes' => ['/publications']]);
    $guard = app(ApprovedSourceUrlGuard::class);

    expect($guard->validate('https://data.example/publications/budget.pdf', $endpoint)->url)->toEndWith('/publications/budget.pdf')
        ->and(fn () => $guard->validate('https://data.example/private/budget.pdf', $endpoint))->toThrow(UnsafeSourceUrl::class)
        ->and(fn () => $guard->validate('https://data.example/publications/%2e%2e/private.pdf', $endpoint))->toThrow(UnsafeSourceUrl::class);
});

it('revalidates redirects and rejects an allowlisted redirect that resolves privately', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34'], 'internal.example' => ['127.0.0.1']]);
    $endpoint = SourceEndpoint::factory()->make(['allowed_hosts' => ['data.example', 'internal.example']]);
    Http::fake([
        'https://data.example/start' => Http::response('', 302, ['Location' => 'https://internal.example/private']),
    ]);

    expect(fn () => app(SafeHttpTransport::class)->get('https://data.example/start', $endpoint))
        ->toThrow(UnsafeSourceUrl::class);
});

it('canonicalizes the source URL authority so the request host matches the pinned host', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make();

    $validated = app(ApprovedSourceUrlGuard::class)
        ->validate('https://DATA.example.:443/publications/budget%20one.pdf?q=%2Fa#frag', $endpoint);

    expect($validated->url)->toBe('https://data.example/publications/budget%20one.pdf?q=%2Fa')
        ->and($validated->host)->toBe('data.example')
        ->and($validated->port)->toBe(443);
});

it('rejects a non-ascii source host that curl would punycode after validation', function (): void {
    bindSourceAddresses(['münchen.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make(['allowed_hosts' => ['münchen.example']]);

    expect(fn () => app(ApprovedSourceUrlGuard::class)->validate('https://münchen.example/file.pdf', $endpoint))
        ->toThrow(UnsafeSourceUrl::class, 'ASCII domain name');
});

it('pins the resolved address to the exact host used for the request', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make();
    $pin = null;

    Http::fake(function (ClientRequest $request, array $options) use (&$pin) {
        $pin = array_values($options['curl'] ?? [])[0][0] ?? null;

        return Http::response('body', 200, ['Content-Type' => 'text/plain']);
    });

    $response = app(SafeHttpTransport::class)->get('https://data.example./publication.pdf', $endpoint);

    expect($response->url)->toBe('https://data.example/publication.pdf')
        ->and($pin)->toBe('data.example:443:93.184.216.34');

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://data.example/publication.pdf');
});

it('refuses to fetch a source when resolved-address pinning is unavailable', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    config()->set('civiclens.ingestion.pin_resolved_address', false);
    $endpoint = SourceEndpoint::factory()->make();
    Http::fake(['https://data.example/publication.pdf' => Http::response('body', 200, ['Content-Type' => 'text/plain'])]);

    expect(fn () => app(SafeHttpTransport::class)->get('https://data.example/publication.pdf', $endpoint))
        ->toThrow(UnsafeSourceUrl::class, 'pinned resolved address');

    Http::assertNothingSent();
});

it('warns when operators explicitly allow unpinned ingestion egress', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    config()->set('civiclens.ingestion.pin_resolved_address', false);
    config()->set('civiclens.ingestion.allow_unpinned_egress', true);
    Log::spy();
    $endpoint = SourceEndpoint::factory()->make();
    $captured = 'unset';

    Http::fake(function (ClientRequest $request, array $options) use (&$captured) {
        $captured = $options['curl'] ?? null;

        return Http::response('body', 200, ['Content-Type' => 'text/plain']);
    });

    expect(app(SafeHttpTransport::class)->get('https://data.example/publication.pdf', $endpoint)->content)->toBe('body')
        ->and($captured)->toBeNull();

    Log::shouldHaveReceived('warning')->once();
});

it('stops responses that exceed the configured content limit', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make(['max_content_bytes' => 8]);
    Http::fake(['https://data.example/file' => Http::response('123456789', 200, ['Content-Type' => 'text/plain'])]);

    expect(fn () => app(SafeHttpTransport::class)->get('https://data.example/file', $endpoint))
        ->toThrow(AcquisitionFailed::class, 'byte limit');
});

it('rejects a declared PDF whose content is not a PDF', function (): void {
    expect(fn () => app(ArtifactMediaTypeInspector::class)->inspect('application/pdf', 'plain text'))
        ->toThrow(AcquisitionFailed::class, 'does not match');
});

it('extracts only allowlisted links from static discovery documents', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make();
    $resources = app(DiscoveryDocumentParser::class)->extract(
        '<a href="/one.pdf">One</a><a href="https://other.example/two.pdf">Two</a><a href="/one.pdf">Duplicate</a>',
        'text/html',
        'https://data.example/index',
        $endpoint,
    );

    expect($resources)->toHaveCount(1)
        ->and($resources[0]->canonicalUrl)->toBe('https://data.example/one.pdf');
});

it('discovers approved document links from JSON feeds and XML sitemaps', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make();
    $parser = app(DiscoveryDocumentParser::class);

    $json = $parser->extract('{"items":[{"download_url":"https://data.example/budget.pdf"}]}', 'application/json', $endpoint->base_url, $endpoint);
    $xml = $parser->extract('<?xml version="1.0"?><urlset><url><loc>https://data.example/audit.pdf</loc></url></urlset>', 'application/xml', $endpoint->base_url, $endpoint);

    expect($json)->toHaveCount(1)
        ->and($json[0]->canonicalUrl)->toEndWith('/budget.pdf')
        ->and($xml)->toHaveCount(1)
        ->and($xml[0]->canonicalUrl)->toEndWith('/audit.pdf');
});

it('uses crawl validators and treats a 304 response as an unchanged discovery batch', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make(['connector_type' => 'api']);
    Http::fake(['https://data.example/publication.pdf' => Http::response('', 304, ['ETag' => '"abc"'])]);
    $cursor = new CrawlCursor(['etag' => '"abc"']);

    $batch = app(ApiFeedSourceConnector::class)->discover($endpoint, $cursor);

    expect($batch->resources)->toBeEmpty()
        ->and($batch->nextCursor)->toBe($cursor);
    Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader('If-None-Match', '"abc"'));
});

it('does not turn untrusted cursor values into request headers', function (): void {
    $cursor = new CrawlCursor(['etag' => "safe\r\nX-Injected: true", 'last_modified' => str_repeat('x', 501)]);

    expect($cursor->conditionalHeaders())->toBe([]);
});

it('creates immutable checksum-deduplicated private artifact versions', function (): void {
    Storage::fake('local');
    Queue::fake();
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->create();
    $content = 'approved public source artifact';

    app()->instance(ArtifactFetcher::class, new class($content) implements ArtifactFetcher
    {
        public function __construct(private readonly string $content) {}

        public function fetch(DiscoveredResource $resource): AcquisitionResult
        {
            return new AcquisitionResult(
                $resource->canonical_url,
                $this->content,
                'application/pdf',
                strlen($this->content),
                hash('sha256', $this->content),
                ['Content-Type' => ['application/pdf'], 'Set-Cookie' => ['secret=value']],
                new MalwareScanResult('clean'),
            );
        }
    });

    $acquisition = app(SourceAcquisitionService::class);
    $firstRun = $acquisition->discover($endpoint);
    Queue::assertPushed(FetchDiscoveredResourceArtifact::class);
    $resource = DiscoveredResource::query()->firstOrFail();
    $artifact = $acquisition->acquire($resource, $firstRun);

    $secondRun = $acquisition->discover($endpoint->fresh());
    $sameArtifact = $acquisition->acquire($resource->fresh(), $secondRun);

    expect($sameArtifact->is($artifact))->toBeTrue()
        ->and(SourceArtifactVersion::query()->count())->toBe(1)
        ->and($artifact->response_headers)->not->toHaveKey('set-cookie')
        ->and($artifact->delete())->toBeFalse();
    Storage::disk('local')->assertExists($artifact->storage_path);
});

it('quarantines artifacts when malware scanning cannot establish a clean result', function (): void {
    Storage::fake('local');
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->create();
    $resource = DiscoveredResource::factory()->for($endpoint, 'endpoint')->create();
    $run = $endpoint->crawlRuns()->create([
        'uuid' => fake()->uuid(),
        'status' => 'fetching',
        'discovered_count' => 1,
        'started_at' => now(),
    ]);

    app()->instance(ArtifactFetcher::class, new class implements ArtifactFetcher
    {
        public function fetch(DiscoveredResource $resource): AcquisitionResult
        {
            return new AcquisitionResult(
                $resource->canonical_url,
                'unscanned',
                'application/pdf',
                9,
                hash('sha256', 'unscanned'),
                [],
                new MalwareScanResult('unavailable', 'Scanner offline'),
            );
        }
    });

    $artifact = app(SourceAcquisitionService::class)->acquire($resource, $run);

    expect($artifact->is_quarantined)->toBeTrue()
        ->and($artifact->storage_path)->toContain('/quarantine/')
        ->and($resource->fresh()->status)->toBe('quarantined');
});

it('refuses to place acquisition snapshots on a public storage disk', function (): void {
    Storage::fake('public');
    config()->set('civiclens.ingestion.artifact_disk', 'public');
    $endpoint = SourceEndpoint::factory()->create();
    $resource = DiscoveredResource::factory()->for($endpoint, 'endpoint')->create();
    $run = $endpoint->crawlRuns()->create([
        'uuid' => fake()->uuid(),
        'status' => 'fetching',
        'discovered_count' => 1,
        'started_at' => now(),
    ]);

    app()->instance(ArtifactFetcher::class, new class implements ArtifactFetcher
    {
        public function fetch(DiscoveredResource $resource): AcquisitionResult
        {
            return new AcquisitionResult(
                $resource->canonical_url,
                'clean',
                'text/plain',
                5,
                hash('sha256', 'clean'),
                [],
                new MalwareScanResult('clean'),
            );
        }
    });

    expect(fn () => app(SourceAcquisitionService::class)->acquire($resource, $run))
        ->toThrow(AcquisitionFailed::class, 'private storage disk');
});

it('restricts source controls to administrators and supports pause and resume', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $adminRole = Role::factory()->create(['slug' => config('civiclens.roles.admin')]);
    $citizenRole = Role::factory()->create(['slug' => config('civiclens.roles.citizen')]);
    $admin = User::factory()->create(['is_active' => true]);
    $citizen = User::factory()->create(['is_active' => true]);
    $admin->roles()->sync([$adminRole->id]);
    $citizen->roles()->sync([$citizenRole->id]);
    $endpoint = SourceEndpoint::factory()->create();

    $this->actingAs($citizen)->get(route('admin.sources.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.sources.index'))->assertOk()->assertSee('Approved source registry');
    $this->actingAs($admin)->patch(route('admin.sources.endpoints.pause', $endpoint))->assertRedirect();
    expect($endpoint->fresh()->paused_at)->not->toBeNull();
    $this->actingAs($admin)->patch(route('admin.sources.endpoints.resume', $endpoint))->assertRedirect();
    expect($endpoint->fresh()->paused_at)->toBeNull();
    expect(SourceActivity::query()->where('actor_id', $admin->id)->pluck('event')->all())
        ->toContain('source.endpoint.paused', 'source.endpoint.resumed');
});

it('validates endpoint DNS before adding it to the registry', function (): void {
    bindSourceAddresses(['data.example' => ['127.0.0.1']]);
    $publisher = SourcePublisher::factory()->create();
    $actor = User::factory()->create();

    expect(fn () => app(SourceRegistryService::class)->createEndpoint([
        'source_publisher_id' => $publisher->id,
        'name' => 'Unsafe endpoint',
        'connector_type' => 'direct_download',
        'base_url' => 'https://data.example/file.pdf',
        'allowed_hosts' => ['data.example'],
        'allowed_path_prefixes' => ['/'],
        'access_decision' => 'robots-and-terms-reviewed',
        'access_reviewed_at' => now()->toDateString(),
        'crawl_interval_minutes' => 1440,
        'rate_limit_per_minute' => 10,
        'timeout_seconds' => 20,
        'max_content_bytes' => 100000,
    ], $actor))->toThrow(UnsafeSourceUrl::class);

    expect(SourceEndpoint::query()->count())->toBe(0);
});

it('submits a form-encoded post to an allowlisted endpoint', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make();
    Http::fake(['https://data.example/servlet' => Http::response('rows', 200, ['Content-Type' => 'text/html'])]);

    $response = app(SafeHttpTransport::class)->post('https://data.example/servlet', $endpoint, ['pageNo' => 1, 'size' => 10]);

    expect($response->content)->toBe('rows');
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_contains((string) ($request->header('Content-Type')[0] ?? ''), 'application/x-www-form-urlencoded')
        && str_contains($request->body(), 'pageNo=1')
        && str_contains($request->body(), 'size=10'));
});

it('applies the same host allowlist to a post as to a get', function (): void {
    bindSourceAddresses(['other.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make();

    expect(fn () => app(SafeHttpTransport::class)->post('https://other.example/servlet', $endpoint, ['a' => 1]))
        ->toThrow(UnsafeSourceUrl::class);
    Http::assertNothingSent();
});

it('pins the resolved address for a post and fails closed without one', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make();
    $pin = null;

    Http::fake(function (ClientRequest $request, array $options) use (&$pin) {
        $pin = array_values($options['curl'] ?? [])[0][0] ?? null;

        return Http::response('ok', 200, ['Content-Type' => 'text/html']);
    });

    app(SafeHttpTransport::class)->post('https://data.example/servlet', $endpoint, ['a' => 1]);

    expect($pin)->toBe('data.example:443:93.184.216.34');

    config()->set('civiclens.ingestion.pin_resolved_address', false);

    expect(fn () => app(SafeHttpTransport::class)->post('https://data.example/servlet', $endpoint, ['a' => 1]))
        ->toThrow(UnsafeSourceUrl::class, 'pinned resolved address');
});

it('revalidates a post redirect and rejects one resolving privately', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34'], 'internal.example' => ['127.0.0.1']]);
    $endpoint = SourceEndpoint::factory()->make(['allowed_hosts' => ['data.example', 'internal.example']]);
    Http::fake(['https://data.example/servlet' => Http::response('', 307, ['Location' => 'https://internal.example/x'])]);

    expect(fn () => app(SafeHttpTransport::class)->post('https://data.example/servlet', $endpoint, ['a' => 1]))
        ->toThrow(UnsafeSourceUrl::class);
});

it('drops the form body when a redirect converts the method to get', function (): void {
    // 301, 302 and 303 are treated as GET by every real client. Resubmitting a
    // form body to a different resource would be a different request than the
    // one that was authorised.
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make();
    $methods = [];

    Http::fake(function (ClientRequest $request) use (&$methods) {
        $methods[] = $request->method();

        return count($methods) === 1
            ? Http::response('', 302, ['Location' => 'https://data.example/moved'])
            : Http::response('ok', 200, ['Content-Type' => 'text/html']);
    });

    app(SafeHttpTransport::class)->post('https://data.example/servlet', $endpoint, ['a' => 1]);

    expect($methods)->toBe(['POST', 'GET']);
});

it('preserves the form body across a 307 redirect', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make();
    $methods = [];

    Http::fake(function (ClientRequest $request) use (&$methods) {
        $methods[] = $request->method();

        return count($methods) === 1
            ? Http::response('', 307, ['Location' => 'https://data.example/moved'])
            : Http::response('ok', 200, ['Content-Type' => 'text/html']);
    });

    app(SafeHttpTransport::class)->post('https://data.example/servlet', $endpoint, ['a' => 1]);

    expect($methods)->toBe(['POST', 'POST']);
});

it('bounds post response bytes exactly as it bounds a get', function (): void {
    bindSourceAddresses(['data.example' => ['93.184.216.34']]);
    $endpoint = SourceEndpoint::factory()->make(['max_content_bytes' => 8]);
    Http::fake(['https://data.example/servlet' => Http::response('123456789', 200, ['Content-Type' => 'text/html'])]);

    expect(fn () => app(SafeHttpTransport::class)->post('https://data.example/servlet', $endpoint, ['a' => 1]))
        ->toThrow(AcquisitionFailed::class, 'byte limit');
});
