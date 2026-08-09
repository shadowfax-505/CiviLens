<?php

use App\Exceptions\Extraction\ExtractionFailed;
use App\Services\Extraction\NativeExtractorRegistry;
use App\Services\Extraction\SpreadsheetNativeExtractor;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Written rather than committed as a binary, so the contents of the fixture are
 * visible in the test that depends on them.
 *
 * @param  array<string, list<list<string|int|float|null>>>  $sheets
 */
function spreadsheetFixture(array $sheets): string
{
    $book = new Spreadsheet;
    $book->removeSheetByIndex(0);

    foreach ($sheets as $title => $rows) {
        $sheet = $book->createSheet();
        $sheet->setTitle($title);

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 1], $value);
            }
        }
    }

    $path = sys_get_temp_dir().'/civiclens-sheet-'.bin2hex(random_bytes(6)).'.xlsx';
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    return $path;
}

it('is the extractor the registry picks for spreadsheets', function (): void {
    // Before this nothing claimed them: xls and xlsx were accepted for
    // acquisition and then threw at extraction, so the artifact sat unread.
    $registry = app(NativeExtractorRegistry::class);

    expect($registry->for('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))
        ->toBeInstanceOf(SpreadsheetNativeExtractor::class)
        ->and($registry->for('application/vnd.ms-excel'))->toBeInstanceOf(SpreadsheetNativeExtractor::class)
        ->and($registry->for('text/csv'))->toBeInstanceOf(SpreadsheetNativeExtractor::class);
});

it('reads rows and columns rather than flattening them', function (): void {
    $path = spreadsheetFixture(['Tenders' => [
        ['Reference', 'Agency', 'Value'],
        ['OTM-2026-114', 'Roads and Highways', 4500000],
    ]]);

    $lines = explode("\n", app(SpreadsheetNativeExtractor::class)->extract($path)->pages[0]->text);

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toBe("Reference\tAgency\tValue")
        ->and($lines[1])->toBe("OTM-2026-114\tRoads and Highways\t4500000");

    unlink($path);
});

it('gives each sheet its own page', function (): void {
    $path = spreadsheetFixture([
        'Awards' => [['Supplier', 'Amount'], ['Alpha Ltd', 120000]],
        'Amendments' => [['Reference', 'Reason'], ['OTM-1', 'Scope revised']],
    ]);

    $result = app(SpreadsheetNativeExtractor::class)->extract($path);

    expect($result->pageCount())->toBe(2)
        ->and($result->pages[0]->text)->toContain('Alpha Ltd')
        ->and($result->pages[1]->text)->toContain('Scope revised')
        ->and($result->engine)->toBe('phpspreadsheet');

    unlink($path);
});

it('reports a formula rather than evaluating it', function (): void {
    // A formula is the publisher's instruction to compute something. Running it
    // would let a fetched file decide what this process does, so the cell is
    // read as what it says rather than as what it would produce.
    $path = spreadsheetFixture(['Sheet' => [['=1+1']]]);

    expect(app(SpreadsheetNativeExtractor::class)->extract($path)->pages[0]->text)
        ->toBe('=1+1');

    unlink($path);
});

it('stops reading beyond the configured row and column bounds', function (): void {
    config([
        'civiclens.extraction.spreadsheet.max_rows' => 2,
        'civiclens.extraction.spreadsheet.max_columns' => 2,
    ]);

    $path = spreadsheetFixture(['Sheet' => [
        ['a1', 'b1', 'c1'],
        ['a2', 'b2', 'c2'],
        ['a3', 'b3', 'c3'],
    ]]);

    $text = app(SpreadsheetNativeExtractor::class)->extract($path)->pages[0]->text;

    expect($text)->toBe("a1\tb1\na2\tb2")
        ->and($text)->not->toContain('c1')
        ->and($text)->not->toContain('a3');

    unlink($path);
});

it('drops rows that hold nothing', function (): void {
    $path = spreadsheetFixture(['Sheet' => [
        ['Reference', 'Agency'],
        [null, null],
        ['OTM-2', 'Public Works'],
    ]]);

    expect(app(SpreadsheetNativeExtractor::class)->extract($path)->pages[0]->text)
        ->toBe("Reference\tAgency\nOTM-2\tPublic Works");

    unlink($path);
});

it('refuses an archive holding more entries than a spreadsheet needs', function (): void {
    config(['civiclens.extraction.spreadsheet.max_archive_entries' => 3]);

    $path = spreadsheetFixture(['Sheet' => [['a']]]);

    expect(fn () => app(SpreadsheetNativeExtractor::class)->extract($path))
        ->toThrow(ExtractionFailed::class, 'too many entries');

    unlink($path);
});

it('refuses an archive that expands beyond the permitted size', function (): void {
    // The check is on the entry table, so the decision is reached without
    // inflating anything — which is the point, since inflating it is the cost
    // being avoided.
    config(['civiclens.extraction.spreadsheet.max_uncompressed_bytes' => 64]);

    $path = spreadsheetFixture(['Sheet' => [['a']]]);

    expect(fn () => app(SpreadsheetNativeExtractor::class)->extract($path))
        ->toThrow(ExtractionFailed::class, 'expands beyond');

    unlink($path);
});

it('refuses an archive that expands at an implausible ratio', function (): void {
    $path = sys_get_temp_dir().'/civiclens-bomb-'.bin2hex(random_bytes(6)).'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', str_repeat('0', 2_000_000));
    $zip->close();

    expect(fn () => app(SpreadsheetNativeExtractor::class)->extract($path))
        ->toThrow(ExtractionFailed::class, 'implausible ratio');

    unlink($path);
});

it('fails loudly when the document cannot be read', function (): void {
    expect(fn () => app(SpreadsheetNativeExtractor::class)->extract('/nonexistent/book.xlsx'))
        ->toThrow(ExtractionFailed::class);
});

it('reads a csv as rows and columns', function (): void {
    // csv now routes here rather than to the plain-text extractor, because a
    // csv is a table and the columns are the point of it.
    $path = sys_get_temp_dir().'/civiclens-sheet-'.bin2hex(random_bytes(6)).'.csv';
    file_put_contents($path, "Reference,Agency\nOTM-1,Roads and Highways\n");

    expect(app(SpreadsheetNativeExtractor::class)->extract($path)->pages[0]->text)
        ->toBe("Reference\tAgency\nOTM-1\tRoads and Highways");

    unlink($path);
});

it('reads a file that is not really a spreadsheet as delimited text', function (): void {
    // Prose in a file named .xlsx is read as a single-column sheet rather than
    // rejected. Deciding that a body does not match its declared media type is
    // acquisition's job, and SourceContentValidator already does it; refusing
    // here as well would only mean a headerless csv could not be read.
    $path = sys_get_temp_dir().'/civiclens-notasheet-'.bin2hex(random_bytes(6)).'.xlsx';
    file_put_contents($path, 'this is not a spreadsheet');

    expect(app(SpreadsheetNativeExtractor::class)->extract($path)->pages[0]->text)
        ->toContain('spreadsheet');

    unlink($path);
});
