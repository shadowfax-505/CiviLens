<?php

namespace App\Data\Extraction;

final readonly class RecognizedWord
{
    public function __construct(
        public string $text,
        public float $confidence,
    ) {}
}
