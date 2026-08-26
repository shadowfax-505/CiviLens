<?php

namespace App\Services\Ingestion;

use App\Models\DiscoveredResource;
use App\Models\SourceArtifactVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Documents linked from pages already in the archive.
 *
 * Discovery reads an endpoint's listing and stops there, so a document linked
 * from a page *below* the listing is never seen. On this corpus that left 251
 * documents unfetched inside pages already held — among them
 * `cag.org.bd/storage/app/media/...`, reached from cbad's pages, which is the
 * index ADR-020 records CAG as not publishing. The second document publisher
 * this project has wanted for weeks was sitting in acquisitions it already had.
 *
 * Depth one, and only from what is archived. This does not crawl: it reads
 * bytes already fetched and proposes what they point at. Every proposal is
 * checked against the endpoint's own allowlist and path prefixes before it is
 * recorded, and recorded as a resource rather than fetched — so acquisition,
 * with the whole of its address pinning, byte ceilings and malware scanning,
 * remains the only thing that ever fetches anything.
 */
class ArchivedPageLinkHarvester
{
    /** What a document link looks like. Anything else is navigation. */
    private const DOCUMENT_LINK = '~href\s*=\s*["\']([^"\']+\.(?:pdf|xlsx?|docx?|csv))["\']~i';

    /** Pages whose bytes we read to find links. */
    private const READABLE_MEDIA_TYPES = ['text/html'];

    public function __construct(
        private readonly ApprovedSourceUrlGuard $guard,
    ) {}

    /**
     * @return array{scanned: int, proposed: int, recorded: int, refused: int, already_known: int}
     */
    public function harvest(bool $apply = false, int $limit = 5000): array
    {
        $known = DiscoveredResource::query()
            ->pluck('canonical_url')
            ->map(fn ($url): string => $this->key((string) $url))
            ->flip();

        $scanned = 0;
        $proposed = 0;
        $recorded = 0;
        $refused = 0;
        $alreadyKnown = 0;

        SourceArtifactVersion::query()
            ->whereIn('media_type', self::READABLE_MEDIA_TYPES)
            ->where('is_quarantined', false)
            ->with('discoveredResource.endpoint')
            ->orderBy('id')
            ->limit($limit)
            ->chunkById(100, function ($artifacts) use (&$scanned, &$proposed, &$recorded, &$refused, &$alreadyKnown, $known, $apply): void {
                foreach ($artifacts as $artifact) {
                    $resource = $artifact->discoveredResource;
                    $endpoint = $resource?->endpoint;

                    if ($resource === null || $endpoint === null) {
                        continue;
                    }

                    $disk = Storage::disk((string) $artifact->storage_disk);

                    if (! $disk->exists((string) $artifact->storage_path)) {
                        continue;
                    }

                    $scanned++;

                    foreach ($this->linksIn((string) $disk->get((string) $artifact->storage_path), (string) $resource->canonical_url) as $url) {
                        $proposed++;

                        if ($known->has($this->key($url))) {
                            $alreadyKnown++;

                            continue;
                        }

                        // The endpoint's own allowlist decides, exactly as it
                        // does for anything discovery proposes. A page can link
                        // anywhere, and following a link off the allowlist would
                        // make the archive the crawler's instruction set.
                        try {
                            $this->guard->validate($url, $endpoint);
                        } catch (Throwable) {
                            $refused++;

                            continue;
                        }

                        if (! $apply) {
                            $recorded++;

                            continue;
                        }

                        DiscoveredResource::query()->updateOrCreate(
                            [
                                'source_endpoint_id' => $endpoint->getKey(),
                                'canonical_url_hash' => hash('sha256', $url),
                            ],
                            [
                                'source_crawl_run_id' => $resource->source_crawl_run_id,
                                'canonical_url' => $url,
                                // Where it was found, which is what makes this
                                // traceable back to a page somebody can read.
                                'discovery_url' => (string) $resource->canonical_url,
                                'resource_type' => 'document',
                                'status' => 'queued',
                                'last_seen_at' => now(),
                                'metadata' => ['found_in' => (int) $artifact->getKey()],
                            ],
                        );

                        $recorded++;
                    }
                }
            });

        return [
            'scanned' => $scanned,
            'proposed' => $proposed,
            'recorded' => $recorded,
            'refused' => $refused,
            'already_known' => $alreadyKnown,
        ];
    }

    /**
     * @return list<string>
     */
    private function linksIn(string $html, string $pageUrl): array
    {
        if (preg_match_all(self::DOCUMENT_LINK, $html, $matches) !== 1 && $matches[1] === []) {
            return [];
        }

        $links = [];

        foreach ($matches[1] as $href) {
            $resolved = $this->resolve(html_entity_decode($href), $pageUrl);

            if ($resolved !== null) {
                $links[$resolved] = true;
            }
        }

        return array_keys($links);
    }

    private function resolve(string $href, string $pageUrl): ?string
    {
        $href = trim($href);

        if ($href === '' || Str::startsWith($href, ['#', 'mailto:', 'javascript:', 'data:'])) {
            return null;
        }

        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }

        $parts = parse_url($pageUrl);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (Str::startsWith($href, '/')) {
            return $origin.$href;
        }

        $directory = rtrim((string) preg_replace('~/[^/]*$~', '', (string) ($parts['path'] ?? '/')), '/');

        return $origin.$directory.'/'.$href;
    }

    /** Compared without case or trailing slash, which is how the same file arrives twice. */
    private function key(string $url): string
    {
        return rtrim(strtolower(trim($url)), '/');
    }
}
