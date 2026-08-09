<?php

namespace App\Services\Extraction;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Keep a sheet's declared extent from deciding how much memory is used.
 *
 * A spreadsheet declares its own dimensions, and the reader builds cell objects
 * for what it is told exists. A file claiming a million rows costs a million
 * rows of memory before any limit applied after loading could help, so the
 * bound has to be enforced while reading rather than afterwards.
 */
final readonly class BoundedReadFilter implements IReadFilter
{
    public function __construct(private int $maxRows, private int $maxColumns) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($row > $this->maxRows) {
            return false;
        }

        return Coordinate::columnIndexFromString($columnAddress) <= $this->maxColumns;
    }
}
