<?php

namespace App\Services\Ingestion;

use App\Data\Ingestion\TenderListingRow;

/**
 * Parse the public e-GP tender listing into structured rows.
 *
 * The listing is server-rendered table fragments rather than JSON, so this reads
 * what the page shows a visitor. It parses only the public notice fields --
 * identifier, reference, status, nature, date -- and never follows into a
 * document, submits a form, or touches anything behind a login.
 *
 * Treated as untrusted input throughout. The markup is authored by the
 * publisher, so values are decoded, length-bounded, and never interpolated
 * anywhere they could be executed. A malformed row is skipped rather than
 * guessed at: a wrong tender identifier is worse than a missing one, because it
 * would silently attach real evidence to the wrong procurement.
 */
class TenderListingParser
{
    private const MAX_FIELD_LENGTH = 300;

    /** @return list<TenderListingRow> */
    public function parse(string $html): array
    {
        $rows = [];

        foreach ($this->rowFragments($html) as $fragment) {
            $row = $this->row($fragment);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return list<string> */
    private function rowFragments(string $html): array
    {
        $matched = preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $matches);

        return $matched === false || $matched === 0 ? [] : $matches[1];
    }

    private function row(string $fragment): ?TenderListingRow
    {
        $cells = $this->cells($fragment);

        if (count($cells) < 2) {
            return null;
        }

        // The identity cell carries the numeric tender id, the official
        // reference, an optional date, and a status label, separated by <br/>.
        $identity = $this->lines($cells[1]);

        if ($identity === []) {
            return null;
        }

        $tenderId = trim(rtrim($identity[0], ','));

        if (preg_match('/^\d{4,12}$/', $tenderId) !== 1) {
            return null;
        }

        $reference = $identity[1] ?? '';
        $date = null;

        // The publisher writes the date several ways: "Date 07-07-26",
        // "Date-01.08.2026", and truncated forms. Accept any of them, and strip
        // whatever matched out of the reference so the reference stays a
        // reference.
        if (preg_match('/,?\s*Date[\s-]+([0-9]{1,4}[-.\/][0-9]{1,2}[-.\/][0-9]{1,4})/i', $reference, $dateMatch) === 1) {
            $date = $dateMatch[1];
            $reference = str_replace($dateMatch[0], '', $reference);
        }

        return new TenderListingRow(
            $tenderId,
            $this->clean(trim($reference, " ,\t")),
            isset($identity[2]) ? $this->clean($identity[2]) : null,
            isset($cells[2]) ? $this->clean(trim($this->lines($cells[2])[0] ?? '', " ,\t")) ?: null : null,
            $date,
        );
    }

    /** @return list<string> */
    private function cells(string $fragment): array
    {
        $matched = preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $fragment, $matches);

        return $matched === false || $matched === 0 ? [] : $matches[1];
    }

    /**
     * Split a cell on line breaks and strip its markup.
     *
     * @return list<string>
     */
    private function lines(string $cell): array
    {
        $withBreaks = preg_replace('/<br\s*\/?>/i', "\n", $cell) ?? $cell;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $clean = $this->clean($line);

            if ($clean !== '') {
                $lines[] = $clean;
            }
        }

        return $lines;
    }

    private function clean(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_substr(trim($collapsed), 0, self::MAX_FIELD_LENGTH);
    }
}
