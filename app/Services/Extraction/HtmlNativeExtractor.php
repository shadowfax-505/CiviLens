<?php

namespace App\Services\Extraction;

use App\Contracts\Extraction\NativeTextExtractor;
use App\Data\Extraction\ExtractedPage;
use App\Data\Extraction\NativeExtractionResult;
use App\Exceptions\Extraction\ExtractionFailed;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Read an HTML page as text, keeping table structure intact.
 *
 * Without this, text/html falls through to the plain-text extractor, which
 * returns the markup itself as if it were prose: every tag, attribute, and
 * inline script counted as content. Density routing then sees a dense page and
 * field extraction reads angle brackets.
 *
 * Tables are the reason this is not a one-line strip_tags. Government tender
 * and audit listings are tables, and flattening a table to running prose
 * destroys the row and column adjacency that says which value belongs to which
 * notice. Rows are emitted one per line with cells tab-separated, so a row
 * survives as a row.
 */
class HtmlNativeExtractor implements NativeTextExtractor
{
    /**
     * Elements carrying no document content. Script and style would otherwise
     * appear as text; navigation and footers repeat on every page of a site and
     * would dominate a short notice.
     */
    private const DISCARDED = ['script', 'style', 'noscript', 'template', 'svg', 'nav', 'footer', 'header', 'form'];

    /** Elements after which a line break is implied by the rendered layout. */
    private const BLOCK = ['p', 'div', 'li', 'tr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'section', 'article', 'blockquote', 'pre'];

    public function supports(string $mediaType): bool
    {
        return $mediaType === 'text/html' || $mediaType === 'application/xhtml+xml';
    }

    public function extract(string $absolutePath): NativeExtractionResult
    {
        $startedAt = microtime(true);
        $html = @file_get_contents($absolutePath);

        if ($html === false) {
            throw new ExtractionFailed('Native extraction could not read the document.');
        }

        $text = trim($html) === '' ? '' : $this->toText($html);

        return new NativeExtractionResult(
            [new ExtractedPage(
                1,
                $text,
                (float) config('civiclens.extraction.default_page_width_points', 595.276),
                (float) config('civiclens.extraction.default_page_height_points', 841.89),
            )],
            'native-html',
            '1',
            (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    private function toText(string $html): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // LIBXML_NONET blocks network retrieval, and neither LIBXML_NOENT nor
        // LIBXML_DTDLOAD is passed, so a declared entity is never substituted.
        // Publisher HTML is untrusted input and must not be able to name a
        // local file or a URL and have it read.
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            // Malformed markup is normal on government sites and is not a
            // failure: fall back to tag stripping rather than losing the page.
            return $this->normalize(strip_tags($html));
        }

        $xpath = new DOMXPath($document);
        $this->discardNoise($xpath);
        $this->foldTables($document, $xpath);
        $this->breakBlocks($document, $xpath);

        return $this->normalize($document->textContent);
    }

    /**
     * Elements matching an XPath query, as a materialised list.
     *
     * DOMXPath::query reports a node set that may contain namespace nodes, and
     * every query here selects elements. Narrowing once means the callers work
     * with the type they actually operate on. The list is materialised because
     * a live node set shifts underneath a loop that removes or replaces nodes.
     *
     * @return list<DOMElement>
     */
    private function elements(DOMXPath $xpath, string $query, ?DOMElement $context = null): array
    {
        $nodes = $xpath->query($query, $context);

        if ($nodes === false) {
            return [];
        }

        $elements = [];

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function discardNoise(DOMXPath $xpath): void
    {
        foreach ($this->elements($xpath, '//'.implode('|//', self::DISCARDED)) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * Replace each table with its rows as text, one row per line.
     *
     * Innermost tables are folded first: nested tables are common in older
     * government pages, and folding an outer table first would swallow the
     * inner one's structure into a single cell.
     */
    private function foldTables(DOMDocument $document, DOMXPath $xpath): void
    {
        while (true) {
            $tables = $this->elements($xpath, '//table[not(descendant::table)]');

            if ($tables === []) {
                return;
            }

            foreach ($tables as $table) {
                $table->parentNode?->replaceChild(
                    $document->createTextNode("\n".$this->tableToText($table, $xpath)."\n"),
                    $table,
                );
            }
        }
    }

    private function tableToText(DOMElement $table, DOMXPath $xpath): string
    {
        $lines = [];

        foreach ($this->elements($xpath, './/tr', $table) as $row) {
            $values = [];

            foreach ($this->elements($xpath, './th|./td', $row) as $cell) {
                $values[] = trim($this->collapseSpaces($cell->textContent));
            }

            // A row of empty cells is layout scaffolding, not a record.
            if (implode('', $values) !== '') {
                $lines[] = implode("\t", $values);
            }
        }

        return implode("\n", $lines);
    }

    private function breakBlocks(DOMDocument $document, DOMXPath $xpath): void
    {
        foreach ($this->elements($xpath, '//br') as $break) {
            $break->parentNode?->replaceChild($document->createTextNode("\n"), $break);
        }

        foreach ($this->elements($xpath, '//'.implode('|//', self::BLOCK)) as $block) {
            $block->appendChild($document->createTextNode("\n"));
        }
    }

    /**
     * Collapse spaces without touching tabs or newlines, because those two now
     * carry the column and row structure recovered from the tables.
     */
    private function collapseSpaces(string $text): string
    {
        return (string) preg_replace('/[^\S\t\n]+/u', ' ', $text);
    }

    private function normalize(string $text): string
    {
        $text = $this->collapseSpaces($text);
        $text = (string) preg_replace('/ *\n */u', "\n", $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);

        return trim($text);
    }
}
