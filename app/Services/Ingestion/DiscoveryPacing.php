<?php

namespace App\Services\Ingestion;

/**
 * Wait between discovery requests, at the rate the endpoint states.
 *
 * A seam rather than a bare `sleep()`, so a test can assert the pacing happened
 * without spending the wall-clock time asserting it. The pacing itself is not
 * decoration: the `source-fetch` limiter covers the fetch stage only, so a
 * connector that walks pages in a loop is the one place an endpoint's stated
 * rate limit can be silently ignored — and was.
 */
class DiscoveryPacing
{
    public function wait(int $seconds): void
    {
        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}
