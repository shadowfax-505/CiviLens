<?php

namespace App\Services\Ingestion;

use App\Data\Ingestion\DiscoveredResourceData;
use App\Models\SourceEndpoint;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use JsonException;
use Throwable;

class DiscoveryDocumentParser
{
    public function __construct(private readonly ApprovedSourceUrlGuard $urlGuard) {}

    /** @return list<DiscoveredResourceData> */
    public function extract(string $content, string $mediaType, string $baseUrl, SourceEndpoint $endpoint): array
    {
        $candidates = match (true) {
            str_contains($mediaType, 'json') => $this->jsonUrls($content),
            str_contains($mediaType, 'xml'), str_contains($mediaType, 'rss'), str_contains($mediaType, 'atom') => $this->xmlUrls($content),
            default => $this->htmlUrls($content),
        };

        $resources = [];
        $limit = (int) config('civiclens.ingestion.max_discovered_per_run', 250);

        foreach ($candidates as $candidate) {
            if (count($resources) >= $limit) {
                break;
            }

            try {
                $resolved = (string) UriResolver::resolve(new Uri($baseUrl), new Uri(trim($candidate)));
                $validated = $this->urlGuard->validate($resolved, $endpoint);
            } catch (Throwable) {
                continue;
            }

            $resources[$validated->url] = new DiscoveredResourceData(
                canonicalUrl: $validated->url,
                discoveryUrl: $baseUrl,
                resourceType: $this->resourceType($validated->url),
            );
        }

        return array_values($resources);
    }

    /** @return list<string> */
    private function jsonUrls(string $content): array
    {
        try {
            $decoded = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        $urls = [];
        $queue = [[$decoded, 0]];
        $visited = 0;

        while ($queue !== [] && $visited < 5000) {
            [$value, $depth] = array_shift($queue);
            $visited++;

            if (! is_array($value) || $depth > 12) {
                continue;
            }

            foreach ($value as $key => $child) {
                if (is_string($child) && is_string($key) && in_array(strtolower($key), ['url', 'link', 'href', 'download_url', 'source_url'], true)) {
                    $urls[] = $child;
                } elseif (is_array($child)) {
                    $queue[] = [$child, $depth + 1];
                }
            }
        }

        return $urls;
    }

    /** @return list<string> */
    private function xmlUrls(string $content): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*[local-name()="loc"] | //*[local-name()="link"]/@href | //*[local-name()="link" and not(@href)]');
        $urls = [];

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $urls[] = trim((string) $node->nodeValue);
            }
        }

        return $urls;
    }

    /** @return list<string> */
    private function htmlUrls(string $content): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//a[@href]/@href | //link[@href and @rel="alternate"]/@href');
        $urls = [];

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $urls[] = trim((string) $node->nodeValue);
            }
        }

        return $urls;
    }

    private function resourceType(string $url): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'json', 'xml', 'jpg', 'jpeg', 'png', 'tif', 'tiff'], true)
            ? 'document'
            : 'page';
    }
}
