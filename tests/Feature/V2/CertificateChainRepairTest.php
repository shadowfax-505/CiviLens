<?php

use App\Services\Ingestion\CertificateChainRepair;

it('reports a host that serves nothing rather than pretending it repaired it', function (): void {
    // A silent failure here would produce a bundle that changes nothing and a
    // report saying it worked.
    $result = app(CertificateChainRepair::class)->inspect('no-such-host.invalid');

    expect($result['served'])->toBe(0)
        ->and($result['fetched'])->toBe([])
        ->and($result['reason'])->toBe('No certificate was served.');
});

it('writes the system roots into the bundle, not only the fetched intermediates', function (): void {
    // A bundle holding intermediates alone verifies nothing: the chain has to
    // reach a root that was already trusted.
    $path = storage_path('app/testing/ca-'.bin2hex(random_bytes(4)).'.pem');

    $result = app(CertificateChainRepair::class)->buildBundle([], $path);

    expect(file_exists($path))->toBeTrue()
        ->and(file_get_contents($path))->toContain('BEGIN CERTIFICATE')
        ->and($result['certificates'])->toBe(0);

    unlink($path);
});

it('refuses to verify a host against a bundle that cannot vouch for it', function (): void {
    // The bundle is only worth building if this can still fail.
    $path = storage_path('app/testing/ca-'.bin2hex(random_bytes(4)).'.pem');
    file_put_contents($path, "-----BEGIN CERTIFICATE-----\nnot a certificate\n-----END CERTIFICATE-----\n");

    expect(app(CertificateChainRepair::class)->verifies('no-such-host.invalid', $path))->toBeFalse();

    unlink($path);
});

it('reads the issuer a certificate names, which is what makes the repair possible', function (): void {
    // The leaf itself says where its issuer lives. That pointer is why fetching
    // the missing intermediate is repair rather than guesswork.
    $repair = app(CertificateChainRepair::class);
    $method = (new ReflectionClass($repair))->getMethod('issuerUrls');

    $certificate = <<<'PEM'
    -----BEGIN CERTIFICATE-----
    MIIBhTCCASugAwIBAgIQIRi6zePL6mKjOipn+dNuaTAKBggqhkjOPQQDAjASMRAw
    DgYDVQQDEwdBY21lIENvMB4XDTE3MTAyMDE5NDMwNloXDTE4MTAyMDE5NDMwNlow
    EjEQMA4GA1UEAxMHQWNtZSBDbzBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABD0d
    7VNhbWvZLWPuj/RtHFjvtJBEwOkhbN/BnnE8rnZR8+sbwnc/KhCk3FhnpHZnQz7B
    5aETbbIgmuvewdjvSBSjYzBhMA4GA1UdDwEB/wQEAwICpDATBgNVHSUEDDAKBggr
    BgEFBQcDATAPBgNVHRMBAf8EBTADAQH/MCkGA1UdEQQiMCCCDmxvY2FsaG9zdDo1
    NDUzgg4xMjcuMC4wLjE6NTQ1MzAKBggqhkjOPQQDAgNIADBFAiEA2zpJEPQyz6/l
    Wf86aX6PepsntZv2GYlA5UpabfT2EZICICpJ5h/iI+i341gBmLiAFQOyTDT+/wQc
    6MF9+Yw1Yy0t
    -----END CERTIFICATE-----
    PEM;

    // This one names no issuer, so nothing is fetched and the reason says why
    // rather than reporting an empty repair as a success.
    expect($method->invoke($repair, $certificate))->toBe([]);
});
