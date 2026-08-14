<?php

namespace App\Services\Ingestion;

use App\Data\Ingestion\RobotsDecision;
use App\Models\SourceEndpoint;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Read what a publisher says a crawler may fetch, and hold us to it.
 *
 * Until now this was a string. `SourceRegistryProvisioner` recorded
 * "absent (HTTP 404); no machine-readable restriction published" against every
 * endpoint without ever fetching robots.txt, which was defensible while three
 * hosts had been read by hand and is not once a dozen are registered.
 *
 * Three rules, and the second is the one that matters:
 *
 * - **Absent means unrestricted.** A 404 is the standard's own answer, and it is
 *   recorded as observed rather than presumed.
 * - **Unreadable means no crawl.** If robots.txt cannot be fetched we do not
 *   know what is permitted, and failing open would mean crawling in exactly the
 *   case where permission is unknown.
 * - **A stated Crawl-delay wins where it is stricter**, because it is the
 *   publisher's own rate limit and ours is only a default.
 */
class RobotsPolicy
{
    public function decide(string $url, SourceEndpoint $endpoint, callable $fetch): RobotsDecision
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '/');

        if ($host === '') {
            return RobotsDecision::refused('The URL carries no host to check robots.txt against.');
        }

        try {
            $record = $this->record($host, $fetch);
        } catch (Throwable $exception) {
            // The cause travels with the refusal. Several publishers serve an
            // incomplete certificate chain, which looks identical to a block
            // unless the reason is carried through, and the two want opposite
            // responses.
            return RobotsDecision::refused(
                'robots.txt for '.$host.' could not be read, so nothing is known to be permitted: '
                .str($exception->getMessage())->squish()->limit(120)->toString(),
            );
        }

        if (($record['status'] ?? null) === 'absent') {
            return RobotsDecision::allowed(null, 'absent');
        }

        $rules = is_array($record['rules'] ?? null) ? $record['rules'] : [];
        $match = $this->longestMatch($rules, $path === '' ? '/' : $path);
        $delay = isset($record['crawl_delay']) && is_numeric($record['crawl_delay']) ? (float) $record['crawl_delay'] : null;

        if ($match !== null && $match['type'] === 'disallow') {
            return RobotsDecision::refused(
                'robots.txt for '.$host.' disallows '.$path.' via "Disallow: '.$match['pattern'].'".',
            );
        }

        return RobotsDecision::allowed($delay, 'present');
    }

    /**
     * The parsed policy for a host, cached for its configured lifetime.
     *
     * Caching is an exposure window: a publisher that adds a Disallow keeps
     * being crawled until it expires, which is why the default is short rather
     * than convenient.
     *
     * @return array<string, mixed>
     */
    private function record(string $host, callable $fetch): array
    {
        $key = 'robots:'.$host;
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        // Fetched outside the cache helper so a failure propagates instead of
        // being stored: a publisher unreachable for a minute should not be
        // treated as unreachable for an hour.
        $body = $fetch('https://'.$host.'/robots.txt');

        $record = $body === null
            ? ['status' => 'absent', 'fetched_at' => now()->toIso8601String()]
            : $this->parse($body) + ['status' => 'present', 'fetched_at' => now()->toIso8601String()];

        Cache::put($key, $record, (int) config('civiclens.ingestion.robots_cache_seconds', 3600));

        return $record;
    }

    /**
     * @return array{rules: list<array{type: string, pattern: string}>, crawl_delay: float|null}
     */
    public function parse(string $body): array
    {
        $rules = [];
        $delay = null;
        $applies = false;
        $agent = strtolower((string) config('civiclens.ingestion.robots_agent', 'civiclensbot'));

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', (string) $line));

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                $value = strtolower($value);
                // Our own name wins over the wildcard group; both are honoured.
                $applies = $value === '*' || str_contains($agent, $value) || str_contains($value, $agent);

                continue;
            }

            if (! $applies) {
                continue;
            }

            if ($field === 'crawl-delay' && is_numeric($value)) {
                $delay = max($delay ?? 0.0, (float) $value);
            }

            if (($field === 'disallow' || $field === 'allow') && $value !== '') {
                $rules[] = ['type' => $field, 'pattern' => $value];
            }
        }

        return ['rules' => $rules, 'crawl_delay' => $delay];
    }

    /**
     * The most specific rule that matches, which is how the convention resolves
     * an Allow sitting inside a broader Disallow.
     *
     * @param  list<array{type: string, pattern: string}>|array<mixed>  $rules
     * @return array{type: string, pattern: string}|null
     */
    private function longestMatch(array $rules, string $path): ?array
    {
        $best = null;
        $bestLength = -1;

        foreach ($rules as $rule) {
            if (! is_array($rule) || ! isset($rule['type'], $rule['pattern'])) {
                continue;
            }

            $pattern = (string) $rule['pattern'];

            if (! $this->matches($pattern, $path)) {
                continue;
            }

            $length = mb_strlen($pattern);

            // A tie goes to Allow: the convention resolves an equally specific
            // pair in the crawler's favour.
            if ($length > $bestLength || ($length === $bestLength && $rule['type'] === 'allow')) {
                $best = ['type' => (string) $rule['type'], 'pattern' => $pattern];
                $bestLength = $length;
            }
        }

        return $best;
    }

    private function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $pattern = $anchored ? rtrim($pattern, '$') : $pattern;

        $regex = '';

        foreach (preg_split('//u', $pattern, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $regex .= $character === '*' ? '.*' : preg_quote($character, '/');
        }

        return preg_match('/^'.$regex.($anchored ? '$' : '').'/u', $path) === 1;
    }
}
