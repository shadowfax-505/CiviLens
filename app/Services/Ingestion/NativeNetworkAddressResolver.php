<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\NetworkAddressResolver;

class NativeNetworkAddressResolver implements NetworkAddressResolver
{
    public function resolve(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];

        foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
