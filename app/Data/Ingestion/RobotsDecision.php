<?php

namespace App\Data\Ingestion;

/**
 * Whether a publisher's robots.txt permits a fetch, and why.
 *
 * The reason travels with the refusal so a blocked crawl says which rule
 * blocked it, rather than appearing as an unexplained failure.
 */
final readonly class RobotsDecision
{
    private function __construct(
        public bool $allowed,
        public ?string $reason,
        public ?float $crawlDelaySeconds,
        public string $policy,
    ) {}

    public static function allowed(?float $crawlDelaySeconds, string $policy): self
    {
        return new self(true, null, $crawlDelaySeconds, $policy);
    }

    public static function refused(string $reason): self
    {
        return new self(false, $reason, null, 'refused');
    }
}
