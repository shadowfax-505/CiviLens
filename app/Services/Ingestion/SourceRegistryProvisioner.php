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
                    // have become a fabrication across a dozen. What robots says
                    // is now a runtime question, answered by one setting rather
                    // than asserted per endpoint at registration time.
                    'robots_txt' => 'governed by civiclens.ingestion.honour_robots; see RobotsPolicy',
                    'published_terms' => 'none discoverable at the time of review',
                ],
            ],
        );

        $created = [];

        foreach ($endpoints as $endpoint) {
            // Whether this endpoint needs pausing, which is not the same
            // question as whether it is new.
            //
            // Re-pausing on every run is a real safety property — a changed
            // access decision should stop the crawl until someone looks — and
            // re-pausing when nothing changed turns a catalogue edit into an
            // outage. Adding a new publisher re-paused two live court endpoints
            // mid-crawl, and the paced fetcher would then have skipped them
            // silently on its next restart.
            //
            // So: pause a new endpoint, and pause an existing one whose
            // governance changed. Leave a running endpoint alone otherwise.
            $existing = SourceEndpoint::query()
                ->where('source_publisher_id', $publisher->getKey())
                ->where('base_url', $endpoint['base_url'])
                ->first();

            $pause = ! $existing instanceof SourceEndpoint || $this->governanceChanged($existing, $endpoint);

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
                    'plaintext_override_reason' => $endpoint['plaintext_override_reason'] ?? null,
                    'plaintext_override_recorded_at' => isset($endpoint['plaintext_override_reason']) ? $reviewedAt : null,
                    'access_reviewed_at' => $reviewedAt,
                    'crawl_interval_minutes' => $endpoint['crawl_interval_minutes'] ?? 1440,
                    'rate_limit_per_minute' => $endpoint['rate_limit_per_minute'] ?? 6,
                    'timeout_seconds' => $endpoint['timeout_seconds'] ?? 30,
                    'max_content_bytes' => $endpoint['max_content_bytes'] ?? 20971520,
                    // Paused on creation, and only on creation. A provisioned
                    // endpoint is a recorded decision, not a running crawl, and
                    // resuming it is a separate act an operator performs
                    // knowingly — but pausing one that is already running turns
                    // a catalogue edit into an outage, which is what it did to
                    // two court endpoints mid-crawl.
                    ...($pause ? ['paused_at' => now(), 'health_status' => 'pending'] : []),
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

    /**
     * Whether anything an operator authorised has changed.
     *
     * Deliberately not "did any field change". A renamed endpoint or a revised
     * rate limit is housekeeping; the host allowlist, the path scope, the
     * access decision and the two overrides are the permissions themselves, and
     * a change to one of those is a new decision that has not been reviewed
     * while running.
     *
     * @param  array<string, mixed>  $definition
     */
    private function governanceChanged(SourceEndpoint $existing, array $definition): bool
    {
        $normalise = static fn (mixed $value): mixed => is_array($value)
            ? array_values(array_map('strval', $value))
            : $value;

        foreach (['allowed_hosts', 'allowed_path_prefixes'] as $key) {
            if ($normalise($existing->{$key}) !== $normalise($definition[$key] ?? [])) {
                return true;
            }
        }

        foreach (['access_decision', 'robots_override_reason', 'plaintext_override_reason'] as $key) {
            if ((string) ($existing->{$key} ?? '') !== (string) ($definition[$key] ?? '')) {
                return true;
            }
        }

        return false;
    }
}
