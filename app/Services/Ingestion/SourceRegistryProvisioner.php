<?php

namespace App\Services\Ingestion;

use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use Illuminate\Support\Carbon;

/**
 * Record an operator's reviewed decision to fetch from a publisher.
 *
 * Creating a registry entry is not the same as crawling. Endpoints are created
 * paused, and `civiclens.ingestion.enabled` still gates the scheduler, so a
 * provisioned source does nothing until two further deliberate acts occur.
 *
 * The authorising sentence is stored verbatim in publisher metadata. The
 * `access_decision` column is a 64-character label and cannot hold it, and a
 * truncated authorisation is worse than none: the record exists to show exactly
 * what a person agreed to, not an approximation of it.
 */
class SourceRegistryProvisioner
{
    /**
     * @param  list<array<string, mixed>>  $endpoints
     * @return array<string, mixed>
     */
    public function provision(
        string $slug,
        string $name,
        string $sourceClass,
        string $canonicalUrl,
        string $attribution,
        string $authorisation,
        array $endpoints,
        ?Carbon $reviewedAt = null,
    ): array {
        $reviewedAt ??= now();

        $publisher = SourcePublisher::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'source_class' => $sourceClass,
                'canonical_url' => $canonicalUrl,
                'attribution_name' => $attribution,
                'rights_decision' => 'operator-authorised-public-material',
                'is_active' => true,
                'metadata' => [
                    'authorisation' => $authorisation,
                    'authorisation_recorded_at' => $reviewedAt->toIso8601String(),
                    // Not recorded here any more. This said "absent (HTTP 404)"
                    // about every publisher without ever fetching the file,
                    // which was a claim about three hosts read by hand and would
                    // have become a fabrication across a dozen. RobotsPolicy
                    // fetches it, honours it per request, and records what it
                    // actually found.
                    'robots_txt' => 'fetched and enforced per request; see RobotsPolicy',
                    'published_terms' => 'none discoverable at the time of review',
                ],
            ],
        );

        $created = [];

        foreach ($endpoints as $endpoint) {
            $record = SourceEndpoint::query()->updateOrCreate(
                [
                    'source_publisher_id' => $publisher->getKey(),
                    'base_url' => $endpoint['base_url'],
                ],
                [
                    'name' => $endpoint['name'],
                    'connector_type' => $endpoint['connector_type'],
                    'connector_options' => $endpoint['connector_options'] ?? null,
                    'allowed_hosts' => $endpoint['allowed_hosts'],
                    'allowed_path_prefixes' => $endpoint['allowed_path_prefixes'],
                    'access_decision' => $endpoint['access_decision'],
                    'robots_override_reason' => $endpoint['robots_override_reason'] ?? null,
                    'robots_override_recorded_at' => isset($endpoint['robots_override_reason']) ? $reviewedAt : null,
                    'access_reviewed_at' => $reviewedAt,
                    'crawl_interval_minutes' => $endpoint['crawl_interval_minutes'] ?? 1440,
                    'rate_limit_per_minute' => $endpoint['rate_limit_per_minute'] ?? 6,
                    'timeout_seconds' => $endpoint['timeout_seconds'] ?? 30,
                    'max_content_bytes' => $endpoint['max_content_bytes'] ?? 20971520,
                    // Paused on creation. A provisioned endpoint is a recorded
                    // decision, not a running crawl, and resuming it is a
                    // separate act an operator performs knowingly.
                    'paused_at' => now(),
                    'health_status' => 'pending',
                ],
            );

            $created[] = $record->name;
        }

        return [
            'publisher' => $publisher->slug,
            'source_class' => $publisher->source_class,
            'endpoints' => $created,
            'paused' => true,
            'ingestion_enabled' => (bool) config('civiclens.ingestion.enabled', false),
            'note' => 'Endpoints are created paused. Nothing is fetched until they are resumed and civiclens.ingestion.enabled is true.',
        ];
    }
}
