<?php

use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Exceptions\Ingestion\UnsafeSourceUrl;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\SafeHttpTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->instance(NetworkAddressResolver::class, new class implements NetworkAddressResolver
    {
        public function resolve(string $host): array
        {
            return $host === 'data.example' ? ['203.0.113.10'] : [];
        }
    });

    robotsFor('data.example', "User-agent: *\nDisallow: /\n");
    Http::fake(['https://data.example/*' => Http::response('body', 200, ['Content-Type' => 'text/csv'])]);
});

function overrideEndpoint(?string $reason): SourceEndpoint
{
    return SourceEndpoint::factory()->create([
        'base_url' => 'https://data.example/datasets/list.csv',
        'allowed_hosts' => ['data.example'],
        'allowed_path_prefixes' => ['/datasets'],
        'rate_limit_per_minute' => 60,
        'robots_override_reason' => $reason,
        'robots_override_recorded_at' => $reason === null ? null : now(),
    ]);
}

it('still refuses a disallowed path when no decision has been recorded', function (): void {
    // The default has to stay refusal, or the override is indistinguishable
    // from never having enforced robots at all.
    expect(fn () => app(SafeHttpTransport::class)->get('https://data.example/datasets/list.csv', overrideEndpoint(null)))
        ->toThrow(UnsafeSourceUrl::class, 'disallows');
});

it('fetches when an operator has recorded a decision to', function (): void {
    $response = app(SafeHttpTransport::class)->get(
        'https://data.example/datasets/list.csv',
        overrideEndpoint('Operator decision on legal advice: non-commercial public-benefit research.'),
    );

    expect($response->status)->toBe(200);
});

it('logs every fetch made against a publisher stated wishes', function (): void {
    // A source taken against what a publisher asked for has to be identifiable
    // later rather than blending in with the rest of the corpus.
    Log::spy();

    app(SafeHttpTransport::class)->get(
        'https://data.example/datasets/list.csv',
        overrideEndpoint('Operator decision on legal advice.'),
    );

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'robots.txt disallows')
            && $context['operator_reason'] === 'Operator decision on legal advice.'
            && str_contains((string) $context['robots_said'], 'Disallow'));
});

it('applies the decision to one endpoint and not to its neighbours', function (): void {
    // Per endpoint rather than global: a switch turning robots off everywhere
    // would take sources nobody decided about.
    $decided = overrideEndpoint('Operator decision on legal advice.');
    $undecided = overrideEndpoint(null);

    expect(app(SafeHttpTransport::class)->get($decided->base_url, $decided)->status)->toBe(200)
        ->and(fn () => app(SafeHttpTransport::class)->get($undecided->base_url, $undecided))
        ->toThrow(UnsafeSourceUrl::class);
});
