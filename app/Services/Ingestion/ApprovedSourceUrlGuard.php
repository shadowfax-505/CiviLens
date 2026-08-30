<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Data\Ingestion\ValidatedSourceUrl;
use App\Exceptions\Ingestion\UnsafeSourceUrl;
use App\Models\SourceEndpoint;

class ApprovedSourceUrlGuard
{
    public function __construct(private readonly NetworkAddressResolver $resolver) {}

    public function validate(string $url, SourceEndpoint $endpoint): ValidatedSourceUrl
    {
        return $this->check($url, $endpoint, enforcePathPrefixes: true);
    }

    /**
     * The same checks without the path allowlist, for robots.txt alone.
     *
     * robots.txt sits at the root by definition, outside whatever prefixes an
     * endpoint is scoped to, so it cannot be fetched under the normal rule. Host
     * allowlisting, address pinning and every other guard still apply: this
     * relaxes one check for one well-known path, not the guard.
     */
    public function validateRobots(string $url, SourceEndpoint $endpoint): ValidatedSourceUrl
    {
        if (! str_ends_with((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/robots.txt')) {
            throw new UnsafeSourceUrl('Only robots.txt may skip the path allowlist.');
        }

        return $this->check($url, $endpoint, enforcePathPrefixes: false);
    }

    private function check(string $url, SourceEndpoint $endpoint, bool $enforcePathPrefixes): ValidatedSourceUrl
    {
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            throw new UnsafeSourceUrl('Source URL is empty or contains control characters.');
        }

        $parts = parse_url($url);

        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';

        // Plaintext by exception, per endpoint, with a recorded reason.
        //
        // HTTPS-only is right: this project's value is that a published figure
        // traces to a document whose bytes are what the publisher served, and
        // over HTTP those bytes can be altered in transit — a checksum of
        // altered bytes is a checksum of the alteration. One government source
        // serves no TLS at all, and the statutes a judgment cites are
        // unreachable without it. The exception is narrow, named and recorded;
        // a global relaxation would weaken provenance for every source
        // including the ones that do serve TLS.
        $plaintextAllowed = is_string($endpoint->plaintext_override_reason)
            && trim($endpoint->plaintext_override_reason) !== '';

        if (! is_array($parts) || ($scheme !== 'https' && ! ($scheme === 'http' && $plaintextAllowed))) {
            throw new UnsafeSourceUrl('Only HTTPS source URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeSourceUrl('Source URLs cannot contain credentials.');
        }

        $defaultPort = $scheme === 'http' ? 80 : 443;

        if (isset($parts['port']) && (int) $parts['port'] !== $defaultPort) {
            throw new UnsafeSourceUrl('Only the standard port for the scheme is allowed.');
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $port = (int) ($parts['port'] ?? $defaultPort);
        $configuredHosts = $endpoint->allowed_hosts;
        $allowedHosts = collect(is_array($configuredHosts) ? $configuredHosts : [])
            ->filter(fn (mixed $allowed): bool => is_string($allowed))
            ->map(fn (string $allowed): string => strtolower(rtrim($allowed, '.')))
            ->values();

        if ($host === '' || ! $allowedHosts->containsStrict($host)) {
            throw new UnsafeSourceUrl('Source URL host is not allowlisted for this endpoint.');
        }

        $path = (string) ($parts['path'] ?? '/');

        for ($iteration = 0; $iteration < 3; $iteration++) {
            $decoded = rawurldecode($path);

            if ($decoded === $path) {
                break;
            }

            $path = $decoded;
        }

        $path = $path ?: '/';

        if (str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path) === 1 || collect(explode('/', $path))->contains(fn (string $segment): bool => in_array($segment, ['.', '..'], true))) {
            throw new UnsafeSourceUrl('Source URL contains an unsafe path.');
        }

        $configuredPrefixes = $endpoint->allowed_path_prefixes;
        $allowedPrefixes = is_array($configuredPrefixes) && $configuredPrefixes !== [] ? $configuredPrefixes : ['/'];
        $pathAllowed = collect($allowedPrefixes)->contains(function (mixed $prefix) use ($path): bool {
            if (! is_string($prefix) || ! str_starts_with($prefix, '/')) {
                return false;
            }

            $normalizedPrefix = rtrim($prefix, '/') ?: '/';

            return $normalizedPrefix === '/' || $path === $normalizedPrefix || str_starts_with($path, $normalizedPrefix.'/');
        });

        if ($enforcePathPrefixes && ! $pathAllowed) {
            throw new UnsafeSourceUrl('Source URL path is not allowlisted for this endpoint.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw new UnsafeSourceUrl('IP-literal source hosts are not allowed.');
        }

        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $host) !== 1) {
            throw new UnsafeSourceUrl('Source URL host must be an ASCII domain name.');
        }

        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            throw new UnsafeSourceUrl('Source host did not resolve to an address.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new UnsafeSourceUrl('Source host resolves to a private or reserved address.');
            }
        }

        return new ValidatedSourceUrl($this->canonicalize($url, $scheme, $host, $port), $host, $addresses[0], $port);
    }

    /**
     * The scheme is carried through rather than assumed.
     *
     * This rebuilt every URL as `https://` because every URL was https. With
     * one endpoint fetched over plaintext by recorded exception, hardcoding it
     * produced `https://host:80/` — TLS aimed at the plaintext port, which
     * fails in a way that looks like the host being down rather than like a bug
     * here.
     */
    private function canonicalize(string $url, string $scheme, string $host, int $port): string
    {
        $withoutFragment = $this->withoutFragment($url);
        $separator = strpos($withoutFragment, '://');

        if ($separator === false) {
            throw new UnsafeSourceUrl('Source URL is not an absolute URL.');
        }

        $authorityStart = $separator + 3;
        $authorityLength = strcspn($withoutFragment, '/?#', $authorityStart);
        $default = $scheme === 'http' ? 80 : 443;
        $authority = $port === $default ? $host : $host.':'.$port;

        return $scheme.'://'.$authority.substr($withoutFragment, $authorityStart + $authorityLength);
    }

    private function withoutFragment(string $url): string
    {
        $position = strpos($url, '#');

        return $position === false ? $url : substr($url, 0, $position);
    }
}
