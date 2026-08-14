<?php

namespace App\Services\Ingestion;

use Throwable;

/**
 * Supply the intermediate certificates a publisher forgot to send.
 *
 * Several Bangladesh government hosts serve only their leaf certificate. A
 * browser and curl paper over it by following the leaf's Authority Information
 * Access pointer and fetching the missing intermediate themselves; OpenSSL does
 * not, so those sites look healthy to a person and unverifiable to this
 * pipeline. Three publishers were unreachable for exactly that reason.
 *
 * This fetches the intermediates the leaf itself names and writes them into a
 * bundle alongside the system roots. That is not a weakening: a certificate is
 * only useful if it chains to a root already trusted and actually signed the
 * leaf, and verification still checks all of it. The alternative on offer —
 * turning verification off — would abandon the guarantee for the sake of a file
 * the publisher merely failed to attach.
 */
class CertificateChainRepair
{
    /**
     * @return array{host: string, served: int, fetched: list<string>, verified: bool, reason: string|null}
     */
    public function inspect(string $host): array
    {
        $leaf = $this->leafCertificate($host);

        if ($leaf === null) {
            return ['host' => $host, 'served' => 0, 'fetched' => [], 'verified' => false, 'reason' => 'No certificate was served.'];
        }

        [$certificate, $served] = $leaf;
        $issuers = $this->issuerUrls($certificate);
        $fetched = [];

        foreach ($issuers as $url) {
            $pem = $this->downloadCertificate($url);

            if ($pem !== null) {
                $fetched[] = $pem;
            }
        }

        return [
            'host' => $host,
            // One means the leaf alone: the intermediate is what is missing.
            'served' => $served,
            'fetched' => $fetched,
            'verified' => false,
            'reason' => $issuers === [] ? 'The certificate names no issuer to fetch.' : null,
        ];
    }

    /**
     * @param  list<string>  $hosts
     * @return array{path: string, hosts: array<string, string>, certificates: int}
     */
    public function buildBundle(array $hosts, string $path): array
    {
        $roots = $this->systemRoots();
        $certificates = [];
        $outcomes = [];

        foreach ($hosts as $host) {
            $result = $this->inspect($host);

            foreach ($result['fetched'] as $pem) {
                $certificates[hash('sha256', $pem)] = $pem;
            }

            $outcomes[$host] = $result['fetched'] === []
                ? ($result['reason'] ?? 'Nothing to add; the chain may already be complete.')
                : 'added '.count($result['fetched']).' intermediate certificate(s)';
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $roots."\n".implode("\n", $certificates)."\n");

        return ['path' => $path, 'hosts' => $outcomes, 'certificates' => count($certificates)];
    }

    /**
     * Verify a host against a bundle, which is the only thing that settles
     * whether the repair worked.
     */
    public function verifies(string $host, string $bundle): bool
    {
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'cafile' => $bundle,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $stream = @stream_socket_client(
            'ssl://'.$host.':443',
            $code,
            $message,
            10,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($stream === false) {
            return false;
        }

        fclose($stream);

        return true;
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function leafCertificate(string $host): ?array
    {
        // Verification is deliberately off here, because the point is to look at
        // a chain that does not verify and find out what it is missing. Nothing
        // is trusted on the strength of this connection.
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $stream = @stream_socket_client('ssl://'.$host.':443', $code, $message, 10, STREAM_CLIENT_CONNECT, $context);

        if ($stream === false) {
            return null;
        }

        $params = stream_context_get_params($stream);
        fclose($stream);

        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;
        $chain = $params['options']['ssl']['peer_certificate_chain'] ?? [];

        if ($certificate === null) {
            return null;
        }

        openssl_x509_export($certificate, $pem);

        return [$pem, is_array($chain) ? count($chain) : 1];
    }

    /**
     * @return list<string>
     */
    private function issuerUrls(string $pem): array
    {
        try {
            $parsed = openssl_x509_parse($pem);
        } catch (Throwable) {
            return [];
        }

        $access = $parsed['extensions']['authorityInfoAccess'] ?? '';

        if (! is_string($access) || $access === '') {
            return [];
        }

        preg_match_all('/CA Issuers - URI:(\S+)/', $access, $matches);

        return array_values(array_unique($matches[1]));
    }

    private function downloadCertificate(string $url): ?string
    {
        try {
            $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 15]]));
        } catch (Throwable) {
            return null;
        }

        if (! is_string($body) || $body === '') {
            return null;
        }

        // Issuers publish DER as often as PEM, and a certificate fetched over
        // plain HTTP is safe to take on either form: it proves nothing by
        // itself and is checked against the roots when it is used.
        if (str_contains($body, 'BEGIN CERTIFICATE')) {
            return trim($body);
        }

        $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($body), 64, "\n").'-----END CERTIFICATE-----';

        return openssl_x509_read($pem) === false ? null : trim($pem);
    }

    private function systemRoots(): string
    {
        foreach ([ini_get('openssl.cafile'), '/etc/ssl/cert.pem', '/etc/ssl/certs/ca-certificates.crt'] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_readable($candidate)) {
                return (string) file_get_contents($candidate);
            }
        }

        return '';
    }
}
