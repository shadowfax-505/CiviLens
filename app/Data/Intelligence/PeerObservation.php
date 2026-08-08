<?php

namespace App\Data\Intelligence;

/**
 * One comparable procurement, reduced to the facts a peer comparison may use.
 *
 * Deliberately narrow. A cohort defined on more attributes than these becomes
 * so specific that every tender is its own peer group, which produces a
 * comparison that always looks unremarkable.
 */
final readonly class PeerObservation
{
    public function __construct(
        public string $reference,
        public float $value,
        public string $procurementNature,
        public string $procurementMethod,
        public string $region,
    ) {}

    public function cohortKey(): string
    {
        return mb_strtolower(trim($this->procurementNature)).'|'
            .mb_strtolower(trim($this->procurementMethod)).'|'
            .mb_strtolower(trim($this->region));
    }
}
