<?php

namespace App\Contracts\Ingestion;

interface NetworkAddressResolver
{
    /** @return list<string> */
    public function resolve(string $host): array;
}
