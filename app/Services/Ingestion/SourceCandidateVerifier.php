<?php

namespace App\Services\Ingestion;

use App\Models\SourceEndpoint;
use Throwable;

/**
 * Ask a candidate source, before registering it, whether it is actually there.
 *
 * A list of sources collected by hand goes stale between collection and use.
 * Of the audit directorates named for this corpus, one host no longer resolves
 * and another puts its listing at a different path from its siblings, so a
 * catalogue that assumed a uniform family would have registered several
 * endpoints that can never yield a document.
 *
 * Read-only: it resolves, reads robots, fetches the listing and counts what the
 * discovery parser would find. It registers nothing and fetches no documents.
 */
class SourceCandidateVerifier
{
    public function __construct(
        private readonly SafeHttpTransport $transport,
        private readonly DiscoveryDocumentParser $parser,
    ) {}

    private function fault(string $message): string
    {
        return match (true) {
            str_contains($message, 'certificate') || str_contains($message, 'SSL') => 'tls-chain-unverified',
            str_contains($message, 'did not resolve') => 'host-does-not-resolve',
            str_contains($message, 'robots.txt') => 'robots-unreadable',
            str_contains($message, 'HTTP 5') => 'server-error',
            str_contains($message, 'HTTP 4') => 'not-published-here',
            default => 'unavailable',
        };
    }

    /**
     * By suffix rather than by fetching: a verification pass that downloaded
     * every candidate to identify it would be a crawl, which is the thing being
     * decided on.
     */
    private function looksLikeDocument(string $url): bool
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));

        foreach (['.pdf', '.xls', '.xlsx', '.csv', '.doc', '.docx'] as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $allowedHosts
     * @return array<string, mixed>
     */
    public function verify(string $url, array $allowedHosts, string $pathPrefix = '/'): array
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));

        // Not persisted: a candidate has no endpoint yet, and creating one to
        // ask whether it is worth creating would leave a row behind for every
        // dead host on the list.
        $endpoint = new SourceEndpoint([
            'name' => 'candidate probe',
            'connector_type' => 'static_html',
            'base_url' => $url,
            'allowed_hosts' => $allowedHosts === [] ? [$host] : $allowedHosts,
            'allowed_path_prefixes' => [$pathPrefix],
            'rate_limit_per_minute' => 4,
            'timeout_seconds' => 30,
            'max_content_bytes' => 5242880,
        ]);

        try {
            $response = $this->transport->get($url, $endpoint, 5242880);
        } catch (Throwable $exception) {
            $message = str($exception->getMessage())->squish()->limit(200)->toString();

            return [
                'url' => $url,
                'reachable' => false,
                // Named apart from an ordinary failure because the remedy is
                // different and the site looks healthy in a browser: several
                // .gov.bd hosts serve an incomplete chain that curl repairs by
                // fetching the missing intermediate and OpenSSL does not.
                'fault' => $this->fault($message),
                'reason' => $message,
                'links_seen' => 0,
                'documents' => 0,
                'hosts' => [],
            ];
        }

        $resources = $this->parser->extract($response->content, $response->mediaType, $response->url, $endpoint);
        $hosts = [];
        $documents = [];

        foreach ($resources as $resource) {
            // A navigation menu is not a document archive. The parser extracts
            // every link, so counting all of them would report a site whose
            // "forty-nine documents" are an about-us page and a contact form as
            // worth registering.
            if (! $this->looksLikeDocument($resource->canonicalUrl)) {
                continue;
            }

            $documents[] = $resource->canonicalUrl;
            $resourceHost = strtolower((string) (parse_url($resource->canonicalUrl, PHP_URL_HOST) ?: ''));
            $hosts[$resourceHost] = ($hosts[$resourceHost] ?? 0) + 1;
        }

        return [
            'url' => $url,
            'reachable' => true,
            'status' => $response->status,
            'reason' => null,
            'links_seen' => count($resources),
            'documents' => count($documents),
            // Which hosts the documents sit on. A directorate listing whose files
            // live on cag.org.bd needs that host allowlisted, and finding out
            // after registering would look like a permission failure.
            'hosts' => $hosts,
            'sample' => array_slice($documents, 0, 3),
        ];
    }
}
