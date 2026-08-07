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
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            throw new UnsafeSourceUrl('Source URL is empty or contains control characters.');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new UnsafeSourceUrl('Only HTTPS source URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeSourceUrl('Source URLs cannot contain credentials.');
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            throw new UnsafeSourceUrl('Only the standard HTTPS port is allowed.');
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $port = (int) ($parts['port'] ?? 443);
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

        if (! $pathAllowed) {
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

        return new ValidatedSourceUrl($this->canonicalize($url, $host, $port), $host, $addresses[0], $port);
    }

    private function canonicalize(string $url, string $host, int $port): string
    {
        $withoutFragment = $this->withoutFragment($url);
        $separator = strpos($withoutFragment, '://');

        if ($separator === false) {
            throw new UnsafeSourceUrl('Source URL is not an absolute HTTPS URL.');
        }

        $authorityStart = $separator + 3;
        $authorityLength = strcspn($withoutFragment, '/?#', $authorityStart);
        $authority = $port === 443 ? $host : $host.':'.$port;

        return 'https://'.$authority.substr($withoutFragment, $authorityStart + $authorityLength);
    }

    private function withoutFragment(string $url): string
    {
        $position = strpos($url, '#');

        return $position === false ? $url : substr($url, 0, $position);
    }
}
