<?php

namespace App\Services\Extraction;

use App\Models\ExtractionPage;
use App\Models\ExtractionTableCell;
use App\Models\SourceArtifactVersion;
use Illuminate\Support\Facades\DB;

/**
 * Give a page's figures back their row and column.
 *
 * The sidecar says where the cells are; the recognized words already stored on
 * the page say what is written and where. Putting the two together is what turns
 *
 *     মোট প্রাপ্তি (৬+৭) ৫৭৩২০২] ৫৪৭২৫৩ | ৮৫৫৩৯৮
 *
 * back into a row with a label and three columns, which is the difference
 * between a figure a reviewer can place and a number nobody can use.
 */
class PageTableAssembler
{
    public function __construct(
        private readonly TableStructureDetector $detector,
        private readonly ArtifactWorkspace $workspace,
        private readonly PageRasterizer $rasterizer,
    ) {}

    /**
     * @return array{tables: int, cells: int, words_placed: int}
     */
    public function assemble(ExtractionPage $page): array
    {
        $words = $page->recognized_words;

        if (! is_array($words) || $words === []) {
            return ['tables' => 0, 'cells' => 0, 'words_placed' => 0];
        }

        $artifact = $page->run?->artifactVersion;

        if (! $artifact instanceof SourceArtifactVersion) {
            return ['tables' => 0, 'cells' => 0, 'words_placed' => 0];
        }

        $materialized = null;
        $image = null;

        try {
            $materialized = $this->workspace->materialize($artifact);
            // The same DPI the recognizer used, taken from the page rather than
            // assumed. Rendering at any other size puts the cell boxes in a
            // different coordinate space from the word boxes and every word
            // lands in the wrong cell, or in none.
            $image = $this->rasterizer->rasterize(
                $materialized,
                (int) $page->page_number,
                $page->recognized_dpi ?? (int) config('civiclens.extraction.ocr.primary_dpi', 150),
            );

            $tables = $this->detector->detect($image);
        } finally {
            if ($image !== null) {
                $this->rasterizer->discard($image);
            }

            $this->workspace->discard($materialized);
        }

        return $this->store($page, $tables, $words);
    }

    /**
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array<string, mixed>>  $words
     * @return array{tables: int, cells: int, words_placed: int}
     */
    private function store(ExtractionPage $page, array $tables, array $words): array
    {
        $cells = 0;
        $placed = 0;

        DB::transaction(function () use ($page, $tables, $words, &$cells, &$placed): void {
            // Re-running a page replaces its cells rather than adding a second
            // copy, so a corrected model or a re-render does not leave two
            // conflicting structures behind.
            ExtractionTableCell::query()->where('extraction_page_id', $page->getKey())->delete();

            foreach ($tables as $table) {
                $tableIndex = (int) ($table['index'] ?? 0);

                foreach ((array) ($table['cells'] ?? []) as $cell) {
                    $box = array_map('intval', (array) ($cell['box'] ?? []));

                    if (count($box) !== 4) {
                        continue;
                    }

                    [$left, $top, $right, $bottom] = $box;
                    $inside = $this->wordsInside($words, $left, $top, $right, $bottom);
                    $placed += count($inside);

                    ExtractionTableCell::query()->create([
                        'extraction_page_id' => $page->getKey(),
                        'table_index' => $tableIndex,
                        'row_index' => (int) ($cell['row'] ?? 0),
                        'column_index' => (int) ($cell['col'] ?? 0),
                        'row_span' => max(1, (int) ($cell['row_span'] ?? 1)),
                        'column_span' => max(1, (int) ($cell['col_span'] ?? 1)),
                        'box_left' => max(0, $left),
                        'box_top' => max(0, $top),
                        'box_right' => max(0, $right),
                        'box_bottom' => max(0, $bottom),
                        'text' => $inside === [] ? null : implode(' ', $inside),
                        'word_count' => count($inside),
                    ]);

                    $cells++;
                }
            }
        });

        return ['tables' => count($tables), 'cells' => $cells, 'words_placed' => $placed];
    }

    /**
     * Words whose centre falls inside the box, read left to right.
     *
     * By centre rather than by corner: a character overhanging a ruling line
     * would otherwise drag its whole word into the neighbouring column, which is
     * exactly the mistake this is meant to correct.
     *
     * @param  list<array<string, mixed>>  $words
     * @return list<string>
     */
    private function wordsInside(array $words, int $left, int $top, int $right, int $bottom): array
    {
        $inside = [];

        foreach ($words as $word) {
            $x = (float) ($word['l'] ?? 0) + ((float) ($word['w'] ?? 0) / 2);
            $y = (float) ($word['y'] ?? 0) + ((float) ($word['h'] ?? 0) / 2);

            if ($x >= $left && $x <= $right && $y >= $top && $y <= $bottom) {
                $inside[] = ['x' => $x, 't' => (string) ($word['t'] ?? '')];
            }
        }

        usort($inside, fn (array $a, array $b): int => $a['x'] <=> $b['x']);

        return array_values(array_filter(array_map(fn (array $w): string => $w['t'], $inside), fn (string $t): bool => $t !== ''));
    }
}
