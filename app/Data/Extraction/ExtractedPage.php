<?php

namespace App\Data\Extraction;

final readonly class ExtractedPage
{
    public function __construct(
        public int $pageNumber,
        public string $text,
        public float $widthPoints,
        public float $heightPoints,
    ) {}

    public function characterCount(): int
    {
        return mb_strlen(trim($this->text));
    }

    public function wordCount(): int
    {
        $words = preg_split('/\s+/u', trim($this->text), -1, PREG_SPLIT_NO_EMPTY);

        if ($words !== false) {
            return count($words);
        }

        // The /u modifier fails on text that is not valid UTF-8, and returning
        // zero for a page full of words is worse than being approximate: a
        // routing or quality decision would be made on a count that looks
        // measured. Splitting on bytes is less precise and cannot silently
        // report nothing.
        $fallback = preg_split('/\s+/', trim($this->text), -1, PREG_SPLIT_NO_EMPTY);

        return $fallback === false ? 0 : count($fallback);
    }

    /**
     * Characters per square inch of page area.
     *
     * Physically comparable across page sizes, unlike a raw character count: a
     * sparse A3 page and a dense A5 page can carry the same number of characters
     * while meaning opposite things about whether a text layer is usable.
     */
    public function density(): float
    {
        $squareInches = ($this->widthPoints / 72) * ($this->heightPoints / 72);

        if ($squareInches <= 0.0) {
            return 0.0;
        }

        return min(99.999999, round($this->characterCount() / $squareInches, 6));
    }
}
