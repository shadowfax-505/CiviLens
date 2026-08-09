<?php

namespace App\Services\Extraction;

use App\Contracts\Extraction\NativeTextExtractor;
use App\Data\Extraction\ExtractedPage;
use App\Data\Extraction\NativeExtractionResult;
use App\Exceptions\Extraction\ExtractionFailed;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;
use ZipArchive;

/**
 * Read a spreadsheet as text, one sheet per page and one row per line.
 *
 * Spreadsheets were downloadable and unreadable: xls and xlsx are accepted
 * media types for acquisition, but nothing claimed them for extraction, so
 * NativeExtractorRegistry threw and the artifact sat unread.
 *
 * The reader is a zip-and-XML parser pointed at files fetched from publishers,
 * so the archive is inspected before it is opened and the sheet is read through
 * a filter that bounds how much of it can be materialised. A spreadsheet is a
 * compact way to describe an enormous amount of data, and the danger is not a
 * malicious publisher so much as an ordinary file with a million empty rows.
 */
class SpreadsheetNativeExtractor implements NativeTextExtractor
{
    public function supports(string $mediaType): bool
    {
        $registry = config('civiclens.extraction.native_media_types', []);

        return is_array($registry) && ($registry[$mediaType] ?? null) === 'spreadsheet';
    }

    public function extract(string $absolutePath): NativeExtractionResult
    {
        $startedAt = microtime(true);

        if (! is_readable($absolutePath)) {
            throw new ExtractionFailed('Native extraction could not read the document.');
        }

        $this->guardArchive($absolutePath);

        $maxRows = max(1, (int) config('civiclens.extraction.spreadsheet.max_rows', 5000));
        $maxColumns = max(1, (int) config('civiclens.extraction.spreadsheet.max_columns', 64));

        try {
            $reader = IOFactory::createReaderForFile($absolutePath);
            // Values only. Styling is not content, and a formula must never be
            // evaluated: the file decides what it computes, and we are only
            // reading what it already says.
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new BoundedReadFilter($maxRows, $maxColumns));

            $spreadsheet = $reader->load($absolutePath);
        } catch (ReaderException $exception) {
            throw new ExtractionFailed('Spreadsheet could not be read: '.$exception->getMessage());
        } catch (Throwable $exception) {
            throw new ExtractionFailed('Spreadsheet could not be read.', 0, $exception);
        }

        $pages = [];

        foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
            $pages[] = new ExtractedPage(
                $index + 1,
                $this->sheetToText($sheet, $maxRows, $maxColumns),
                (float) config('civiclens.extraction.default_page_width_points', 595.276),
                (float) config('civiclens.extraction.default_page_height_points', 841.89),
            );
        }

        $spreadsheet->disconnectWorksheets();

        if ($pages === []) {
            throw new ExtractionFailed('Spreadsheet contains no sheets.');
        }

        return new NativeExtractionResult(
            $pages,
            'phpspreadsheet',
            '1',
            (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    /**
     * Refuse an archive that would cost more to open than it does to hold.
     *
     * xlsx and ods are zip containers, and the reader inflates their parts
     * before any row limit applies. The checks are on the entries themselves so
     * the decision is made without inflating anything.
     */
    private function guardArchive(string $absolutePath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            // Not a zip container. xls is a binary record format and csv is
            // plain text; both are bounded by the acquisition byte ceiling.
            return;
        }

        try {
            $maxEntries = max(1, (int) config('civiclens.extraction.spreadsheet.max_archive_entries', 512));
            $maxBytes = max(1, (int) config('civiclens.extraction.spreadsheet.max_uncompressed_bytes', 268435456));
            $maxRatio = max(1.0, (float) config('civiclens.extraction.spreadsheet.max_compression_ratio', 200));

            if ($zip->numFiles > $maxEntries) {
                throw new ExtractionFailed('Spreadsheet archive contains too many entries.');
            }

            $uncompressed = 0;
            $compressed = 0;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);

                if ($entry === false) {
                    continue;
                }

                $uncompressed += (int) $entry['size'];
                $compressed += (int) $entry['comp_size'];

                if ($uncompressed > $maxBytes) {
                    throw new ExtractionFailed('Spreadsheet archive expands beyond the permitted size.');
                }
            }

            if ($compressed > 0 && $uncompressed / $compressed > $maxRatio) {
                throw new ExtractionFailed('Spreadsheet archive expands at an implausible ratio.');
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * One row per line, cells separated by tabs.
     *
     * The same shape the HTML extractor produces for a table, and for the same
     * reason: a row read as running prose loses which value belonged to which
     * column, and nothing downstream can put that back.
     */
    private function sheetToText(Worksheet $sheet, int $maxRows, int $maxColumns): string
    {
        $lines = [];
        $row = 0;

        foreach ($sheet->getRowIterator() as $sheetRow) {
            if (++$row > $maxRows) {
                break;
            }

            $cells = $sheetRow->getCellIterator();
            $cells->setIterateOnlyExistingCells(true);
            $values = [];
            $column = 0;

            foreach ($cells as $cell) {
                if (++$column > $maxColumns) {
                    break;
                }

                $values[] = $this->cellText($cell);
            }

            // A row of empty cells is spacing, not a record.
            if (trim(implode('', $values)) !== '') {
                $lines[] = rtrim(implode("\t", $values), "\t");
            }
        }

        return implode("\n", $lines);
    }

    private function cellText(Cell $cell): string
    {
        // getValue, never getCalculatedValue: a formula is the publisher's
        // instruction to compute something, and running it would let a fetched
        // file decide what this process does.
        $value = $cell->getValue();

        if ($value === null || is_scalar($value)) {
            return trim((string) $value);
        }

        return trim((string) (is_object($value) && method_exists($value, '__toString') ? $value : ''));
    }
}
