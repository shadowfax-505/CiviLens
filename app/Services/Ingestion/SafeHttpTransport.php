<?php

namespace App\Services\Ingestion;

use App\Data\Ingestion\SafeHttpResponse;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Models\SourceEndpoint;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SafeHttpTransport
{
    public function __construct(private readonly ApprovedSourceUrlGuard $urlGuard) {}

    /** @param array<string, string> $requestHeaders */
    public function get(string $url, SourceEndpoint $endpoint, ?int $maximumBytes = null, array $requestHeaders = []): SafeHttpResponse
    {
        $limit = min(
            max(1, $maximumBytes ?? $endpoint->max_content_bytes),
            (int) config('civiclens.ingestion.absolute_max_content_bytes', 52428800),
        );
        $redirectsRemaining = (int) config('civiclens.ingestion.max_redirects', 3);
        $currentUrl = $url;

        while (true) {
            $validated = $this->urlGuard->validate($currentUrl, $endpoint);
            $response = $this->request($validated->url, $validated->host, $validated->ipAddress, $endpoint, $requestHeaders);

            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                if ($redirectsRemaining <= 0) {
                    throw new AcquisitionFailed('Source exceeded the redirect limit.');
                }

                $location = $response->header('Location');

                if (! is_string($location) || trim($location) === '') {
                    throw new AcquisitionFailed('Source redirect did not include a location.');
                }

                $currentUrl = (string) UriResolver::resolve(new Uri($validated->url), new Uri($location));
                $redirectsRemaining--;

                continue;
            }

            $psrResponse = $response->toPsrResponse();
            $headers = $this->headers($psrResponse->getHeaders());

            if ($response->status() === 304) {
                return new SafeHttpResponse($validated->url, '', 'application/octet-stream', $headers, 304);
            }

            if (! $response->successful()) {
                throw new AcquisitionFailed('Source returned HTTP '.$response->status().'.');
            }

            $contentLength = $response->header('Content-Length');

            if (is_string($contentLength) && ctype_digit($contentLength) && (int) $contentLength > $limit) {
                throw new AcquisitionFailed('Source content exceeds the configured byte limit.');
            }

            $stream = $psrResponse->getBody();
            $content = '';

            while (! $stream->eof()) {
                $content .= $stream->read(min(8192, $limit - strlen($content) + 1));

                if (strlen($content) > $limit) {
                    throw new AcquisitionFailed('Source content exceeds the configured byte limit.');
                }
            }

            $mediaType = strtolower(trim(explode(';', $response->header('Content-Type') ?: 'application/octet-stream')[0]));

            return new SafeHttpResponse($validated->url, $content, $mediaType, $headers, $response->status());
        }
    }

    /** @param array<string, string> $requestHeaders */
    private function request(string $url, string $host, string $ipAddress, SourceEndpoint $endpoint, array $requestHeaders): Response
    {
        $options = [
            'allow_redirects' => false,
            'http_errors' => false,
            'stream' => true,
        ];

        if (defined('CURLOPT_RESOLVE') && (bool) config('civiclens.ingestion.pin_resolved_address', true)) {
            $pinnedAddress = str_contains($ipAddress, ':') ? '['.$ipAddress.']' : $ipAddress;
            $options['curl'] = [CURLOPT_RESOLVE => [$host.':443:'.$pinnedAddress]];
        }

        return Http::accept('*/*')
            ->withUserAgent((string) config('civiclens.ingestion.user_agent'))
            ->withHeaders([...$requestHeaders, 'Accept-Encoding' => 'identity'])
            ->connectTimeout(min(10, max(1, $endpoint->timeout_seconds)))
            ->timeout(max(1, $endpoint->timeout_seconds))
            ->withOptions($options)
            ->get($url);
    }

    /**
     * @param  array<string, array<string>>  $headers
     * @return array<string, list<string>>
     */
    private function headers(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $normalized[$name] = array_values($values);
        }

        return $normalized;
    }
}
