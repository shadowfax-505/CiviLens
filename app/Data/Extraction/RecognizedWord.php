<?php

namespace App\Data\Extraction;

final readonly class RecognizedWord
{
    public function __construct(
        public string $text,
        public float $confidence,
        public int $left = 0,
        public int $top = 0,
        public int $width = 0,
        public int $height = 0,
    ) {}

    public function right(): int
    {
        return $this->left + $this->width;
    }

    public function bottom(): int
    {
        return $this->top + $this->height;
    }

    public function verticalCentre(): float
    {
        return $this->top + ($this->height / 2);
    }

    /**
     * Whether two words sit on the same visual line.
     *
     * Form layouts put a label and its value side by side, so "same line" has to
     * tolerate the baseline jitter of a scan rather than demand identical tops.
     */
    public function sharesLineWith(self $other): bool
    {
        $tolerance = max(1.0, min($this->height, $other->height) * 0.6);

        return abs($this->verticalCentre() - $other->verticalCentre()) <= $tolerance;
    }
}
