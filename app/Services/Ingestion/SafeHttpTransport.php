<?php

namespace App\Services\Ingestion;

use App\Data\Ingestion\SafeHttpResponse;
use App\Data\Ingestion\ValidatedSourceUrl;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Exceptions\Ingestion\UnsafeSourceUrl;
use App\Models\SourceEndpoint;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SafeHttpTransport
{
    /**
     * True only while robots.txt itself is being fetched.
     *
     * Checking robots before fetching robots would never terminate, and the file
     * sits outside the endpoint's allowed path prefixes by definition, so that
     * one fetch is exempt from both checks and from nothing else.
     */
    private bool $fetchingRobots = false;

    public function __construct(
        private readonly ApprovedSourceUrlGuard $urlGuard,
        private readonly RobotsPolicy $robots,
    ) {}

    /** @param array<string, string> $requestHeaders */
    public function get(string $url, SourceEndpoint $endpoint, ?int $maximumBytes = null, array $requestHeaders = []): SafeHttpResponse
    {
        return $this->send($url, $endpoint, null, $maximumBytes, $requestHeaders);
    }

    /**
     * Submit a form-encoded request to an allowlisted endpoint.
     *
     * Some publishers serve their public listings only through a POST form
     * rather than a URL. The safety properties are identical to a GET and are
     * not relaxed for it: the same host allowlist, the same fail-closed address
     * pinning, the same per-hop revalidation, and the same byte ceiling.
     *
     * @param  array<string, scalar>  $form
     * @param  array<string, string>  $requestHeaders
     */
    public function post(string $url, SourceEndpoint $endpoint, array $form, ?int $maximumBytes = null, array $requestHeaders = []): SafeHttpResponse
    {
        return $this->send($url, $endpoint, $form, $maximumBytes, $requestHeaders);
    }

    /**
     * @param  array<string, scalar>|null  $form  null sends a GET
     * @param  array<string, string>  $requestHeaders
     */
    private function send(string $url, SourceEndpoint $endpoint, ?array $form, ?int $maximumBytes, array $requestHeaders): SafeHttpResponse
    {
        $limit = min(
            max(1, $maximumBytes ?? $endpoint->max_content_bytes),
            (int) config('civiclens.ingestion.absolute_max_content_bytes', 52428800),
        );
        $redirectsRemaining = (int) config('civiclens.ingestion.max_redirects', 3);
        $currentUrl = $url;

        while (true) {
            $validated = $this->urlGuard->validate($currentUrl, $endpoint);

            // Checked per hop rather than once: a redirect can land on a path
            // the publisher disallows, and the hop we actually fetch is the one
            // that has to be permitted.
            $this->assertRobotsAllows($validated->url, $endpoint);

            $response = $this->request($validated, $endpoint, $requestHeaders, $form);

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

                // 303 always becomes a GET, and 301/302 are universally treated
                // that way in practice. Only 307/308 preserve the method and
                // body, so a form is dropped for the others rather than being
                // silently resubmitted to a different resource.
                if ($form !== null && ! in_array($response->status(), [307, 308], true)) {
                    $form = null;
                }

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

    /**
     * Refuse a fetch the publisher's robots.txt does not permit.
     *
     * Public because the browser renderer does not send its requests through
     * this class and would otherwise skip the check entirely. A renderer that
     * ignored robots would be a way round the rule rather than an exception to
     * it.
     */
    public function assertMayFetch(string $url, SourceEndpoint $endpoint): void
    {
        $this->assertRobotsAllows($url, $endpoint);
    }

    /**
     * Refuse a fetch the publisher's robots.txt does not permit, saying which
     * rule refused it.
     */
    private function assertRobotsAllows(string $url, SourceEndpoint $endpoint): void
    {
        if ($this->fetchingRobots) {
            return;
        }

        $decision = $this->robots->decide($url, $endpoint, fn (string $robotsUrl): ?string => $this->fetchRobots($robotsUrl, $endpoint));

        if (! $decision->allowed) {
            throw new UnsafeSourceUrl((string) $decision->reason);
        }

        // The publisher's own pace where it is slower than ours. Ours is a
        // default; theirs is a statement.
        if ($decision->crawlDelaySeconds !== null && $decision->crawlDelaySeconds > 0) {
            $stated = (int) floor(60 / max(0.001, $decision->crawlDelaySeconds));

            if ($stated < (int) $endpoint->rate_limit_per_minute) {
                $endpoint->forceFill(['rate_limit_per_minute' => max(1, $stated)])->save();
            }
        }
    }

    /**
     * Fetch robots.txt itself, exempt from the robots check and from the path
     * allowlist, but from nothing else.
     *
     * Requested directly rather than through send(), so there is no redirect
     * chasing and no second robots check to recurse into.
     */
    private function fetchRobots(string $robotsUrl, SourceEndpoint $endpoint): ?string
    {
        $this->fetchingRobots = true;

        try {
            $validated = $this->urlGuard->validateRobots($robotsUrl, $endpoint);
            // Not streamed. Streaming makes Guzzle report a certificate failure
            // as "Connection refused", which sends an operator looking for a
            // firewall when the publisher is actually serving an incomplete
            // chain. robots.txt is small enough not to need it.
            $response = $this->request($validated, $endpoint, [], null, stream: false);

            // Absent is an answer, and the standard's answer is "unrestricted".
            // A redirect or an error page is not a policy either.
            return $response->status() === 200 ? (string) $response->body() : null;
        } finally {
            $this->fetchingRobots = false;
        }
    }

    /**
     * @param  array<string, scalar>|null  $form  null sends a GET
     * @param  array<string, string>  $requestHeaders
     */
    private function request(ValidatedSourceUrl $validated, SourceEndpoint $endpoint, array $requestHeaders, ?array $form = null, bool $stream = true): Response
    {
        $options = [
            'allow_redirects' => false,
            'http_errors' => false,
            'stream' => $stream,
        ];

        $bundle = config('civiclens.ingestion.ca_bundle');

        if (is_string($bundle) && $bundle !== '') {
            $options['verify'] = $bundle;
        }

        if (defined('CURLOPT_RESOLVE') && (bool) config('civiclens.ingestion.pin_resolved_address', true)) {
            $options['curl'] = [CURLOPT_RESOLVE => [$this->resolveEntry($validated)]];
        } elseif (! (bool) config('civiclens.ingestion.allow_unpinned_egress', false)) {
            throw new UnsafeSourceUrl('Refusing to fetch a source without a pinned resolved address.');
        } else {
            Log::warning('Ingestion egress is not address-pinned.', [
                'endpoint_id' => $endpoint->id,
                'host' => $validated->host,
                'ip_address' => $validated->ipAddress,
            ]);
        }

        $request = Http::accept('*/*')
            ->withUserAgent((string) config('civiclens.ingestion.user_agent'))
            ->withHeaders([...$requestHeaders, 'Accept-Encoding' => 'identity'])
            ->connectTimeout(min(10, max(1, $endpoint->timeout_seconds)))
            ->timeout(max(1, $endpoint->timeout_seconds))
            ->withOptions($options);

        return $form === null
            ? $request->get($validated->url)
            : $request->asForm()->post($validated->url, $form);
    }

    private function resolveEntry(ValidatedSourceUrl $validated): string
    {
        $address = str_contains($validated->ipAddress, ':') ? '['.$validated->ipAddress.']' : $validated->ipAddress;

        return $validated->host.':'.$validated->port.':'.$address;
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
