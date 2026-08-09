<?php

namespace App\Data\Ingestion;

final readonly class TenderListingRow
{
    public function __construct(
        public string $tenderId,
        public string $referenceNumber,
        public ?string $status,
        public ?string $procurementNature,
        public ?string $publishedOn,
    ) {}

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'tender_id' => $this->tenderId,
            'reference_number' => $this->referenceNumber,
            'status' => $this->status,
            'procurement_nature' => $this->procurementNature,
            'published_on' => $this->publishedOn,
        ];
    }
}
