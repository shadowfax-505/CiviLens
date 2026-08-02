<?php

namespace App\Services\Ingestion;

use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SourceRegistryService
{
    public function __construct(
        private readonly ApprovedSourceUrlGuard $urlGuard,
        private readonly SourceActivityRecorder $activities,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function createPublisher(array $attributes, User $actor): SourcePublisher
    {
        $url = (string) $attributes['canonical_url'];
        $host = (string) parse_url($url, PHP_URL_HOST);
        $candidate = new SourceEndpoint(['allowed_hosts' => [$host]]);
        $validated = $this->urlGuard->validate($url, $candidate);

        $publisher = SourcePublisher::query()->create([
            ...$attributes,
            'canonical_url' => $validated->url,
            'is_active' => (bool) ($attributes['is_active'] ?? true),
        ]);
        $this->activities->record('source.publisher.created', actor: $actor, publisher: $publisher);

        return $publisher;
    }

    /** @param array<string, mixed> $attributes */
    public function createEndpoint(array $attributes, User $actor): SourceEndpoint
    {
        $configuredHosts = $attributes['allowed_hosts'] ?? [];
        $hosts = collect(is_array($configuredHosts) ? $configuredHosts : [])
            ->map(fn (mixed $host): string => strtolower(rtrim((string) $host, '.')))
            ->unique()
            ->values()
            ->all();
        $configuredPrefixes = $attributes['allowed_path_prefixes'] ?? [];
        $pathPrefixes = collect(is_array($configuredPrefixes) ? $configuredPrefixes : [])
            ->map(fn (mixed $prefix): string => rtrim((string) $prefix, '/') ?: '/')
            ->unique()
            ->values()
            ->all();
        $candidate = new SourceEndpoint([
            ...$attributes,
            'allowed_hosts' => $hosts,
            'allowed_path_prefixes' => $pathPrefixes,
        ]);
        $validated = $this->urlGuard->validate((string) $attributes['base_url'], $candidate);

        return DB::transaction(function () use ($attributes, $validated, $hosts, $pathPrefixes, $actor): SourceEndpoint {
            $endpoint = SourceEndpoint::query()->create([
                ...$attributes,
                'base_url' => $validated->url,
                'allowed_hosts' => $hosts,
                'allowed_path_prefixes' => $pathPrefixes,
                'health_status' => 'pending',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->activities->record('source.endpoint.created', actor: $actor, endpoint: $endpoint);

            return $endpoint;
        });
    }

    public function pause(SourceEndpoint $endpoint, User $actor): void
    {
        $endpoint->update(['paused_at' => now(), 'updated_by' => $actor->id]);
        $this->activities->record('source.endpoint.paused', actor: $actor, endpoint: $endpoint);
    }

    public function resume(SourceEndpoint $endpoint, User $actor): void
    {
        $endpoint->update(['paused_at' => null, 'health_status' => 'pending', 'last_error' => null, 'updated_by' => $actor->id]);
        $this->activities->record('source.endpoint.resumed', actor: $actor, endpoint: $endpoint);
    }

    public function queued(SourceEndpoint $endpoint, User $actor): void
    {
        $this->activities->record('source.crawl.queued', actor: $actor, endpoint: $endpoint);
    }
}
