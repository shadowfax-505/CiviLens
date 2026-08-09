<?php

use App\Jobs\FetchDiscoveredResourceArtifact;
use Illuminate\Queue\Middleware\RateLimited;

it('outlasts the rate limiter without treating waiting as failure', function (): void {
    // A release is backpressure, not an error. With a six-per-minute budget a
    // page of ten notices spends several attempts waiting, and at four attempts
    // every artifact past the sixth failed with MaxAttemptsExceeded before a
    // single request was made. Measured on a live crawl: 60 discovered
    // resources, 60 failed jobs, 0 artifacts fetched.
    $job = new FetchDiscoveredResourceArtifact(1, 1, 1);

    expect($job->tries)->toBeGreaterThanOrEqual(20)
        // Genuine errors must still fail fast rather than retrying to the
        // attempt ceiling.
        ->and($job->maxExceptions)->toBeLessThanOrEqual(3)
        ->and($job->middleware())->toHaveCount(1)
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);
});
