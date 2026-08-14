<?php

use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Exceptions\Ingestion\UnsafeSourceUrl;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\RobotsPolicy;
use App\Services\Ingestion\SafeHttpTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// A public address for the fixture host, so the guard's own resolution check
// passes and what these tests measure is the robots decision alone.
beforeEach(function (): void {
    app()->instance(NetworkAddressResolver::class, new class implements NetworkAddressResolver
    {
        public function resolve(string $host): array
        {
            return $host === 'data.example' ? ['203.0.113.10'] : [];
        }
    });
});

function robotsEndpoint(): SourceEndpoint
{
    return SourceEndpoint::factory()->create([
        'base_url' => 'https://data.example/reports/',
        'allowed_hosts' => ['data.example'],
        'allowed_path_prefixes' => ['/reports'],
        'rate_limit_per_minute' => 60,
    ]);
}

it('refuses a path the publisher disallows, and says which rule refused it', function (): void {
    // The point of reading robots.txt is to be bound by it. A refusal that does
    // not name its rule is indistinguishable from a bug.
    robotsFor('data.example', "User-agent: *\nDisallow: /reports/private\n");

    Http::fake(['https://data.example/*' => Http::response('body', 200, ['Content-Type' => 'text/html'])]);

    expect(fn () => app(SafeHttpTransport::class)->get('https://data.example/reports/private/x.pdf', robotsEndpoint()))
        ->toThrow(UnsafeSourceUrl::class, 'Disallow: /reports/private');
});

it('allows a path the publisher permits inside a broader refusal', function (): void {
    // Allow beats Disallow when it is the more specific rule, which is how the
    // convention lets a publisher open one folder inside a closed tree.
    robotsFor('data.example', "User-agent: *\nDisallow: /reports\nAllow: /reports/public\n");

    Http::fake(['https://data.example/*' => Http::response('body', 200, ['Content-Type' => 'text/html'])]);

    $response = app(SafeHttpTransport::class)->get('https://data.example/reports/public/x.pdf', robotsEndpoint());

    expect($response->status)->toBe(200);
});

it('treats an absent robots.txt as no restriction, because that is what it means', function (): void {
    Http::fake([
        'https://data.example/robots.txt' => Http::response('', 404),
        'https://data.example/*' => Http::response('body', 200, ['Content-Type' => 'text/html']),
    ]);

    $response = app(SafeHttpTransport::class)->get('https://data.example/reports/x.pdf', robotsEndpoint());

    expect($response->status)->toBe(200)
        ->and(Cache::get('robots:data.example')['status'])->toBe('absent');
});

it('refuses to crawl when robots.txt cannot be read at all', function (): void {
    // Failing open would mean crawling in exactly the case where permission is
    // unknown. The publisher has not said we may; silence from the network is
    // not consent.
    Http::fake(fn () => throw new ConnectionException('network unreachable'));

    expect(fn () => app(SafeHttpTransport::class)->get('https://data.example/reports/x.pdf', robotsEndpoint()))
        ->toThrow(UnsafeSourceUrl::class, 'could not be read');
});

it('adopts a stated crawl-delay when it is slower than our own limit', function (): void {
    // Ours is a default; theirs is a statement. One request every thirty seconds
    // is two a minute.
    robotsFor('data.example', "User-agent: *\nCrawl-delay: 30\n");

    Http::fake(['https://data.example/*' => Http::response('body', 200, ['Content-Type' => 'text/html'])]);

    $endpoint = robotsEndpoint();
    app(SafeHttpTransport::class)->get('https://data.example/reports/x.pdf', $endpoint);

    expect($endpoint->refresh()->rate_limit_per_minute)->toBe(2);
});

it('reads the rules addressed to us, not only the wildcard group', function (): void {
    $policy = app(RobotsPolicy::class);

    $parsed = $policy->parse("User-agent: Googlebot\nDisallow: /everything\n\nUser-agent: CivicLensBot\nDisallow: /ours\n");

    expect(collect($parsed['rules'])->pluck('pattern')->all())->toBe(['/ours']);
});

it('ignores comments and blank lines rather than reading them as rules', function (): void {
    $parsed = app(RobotsPolicy::class)->parse("# a note\nUser-agent: *\n\nDisallow: /private # trailing note\n");

    expect($parsed['rules'])->toBe([['type' => 'disallow', 'pattern' => '/private']]);
});
