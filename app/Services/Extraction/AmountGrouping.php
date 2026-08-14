<?php

namespace App\Services\Extraction;

/**
 * Does a figure's comma actually group thousands?
 *
 * A decimal point read as a comma is invisible in the value and enormous in the
 * result: a page reading ৪০০.০০ arrives as "800,00", and treating that comma as
 * a thousands separator turns four hundred into eighty thousand. The characters
 * look ordinary, so nothing downstream notices.
 *
 * Grouping is checkable. Both conventions in this corpus put three digits in the
 * final group — Indian repeats twos before it, 12,34,56,789 and 196,86,36,343;
 * Western uses threes throughout, 1,234,567. A final group of one or two digits
 * is neither, and is where a misread decimal point shows up.
 *
 * This reports the suspicion and never acts on it. Rewriting the comma to a
 * point would be the system deciding the answer to the question it is asking a
 * reviewer.
 */
class AmountGrouping
{
    private const DIGIT = '[\d\x{09E6}-\x{09EF}]';

    public function groupsCorrectly(string $value): bool
    {
        $bare = $this->bare($value);

        if (! str_contains($bare, ',')) {
            return true;
        }

        $digit = self::DIGIT;

        $indian = '/^'.$digit.'{1,3}(?:,'.$digit.'{2})*,'.$digit.'{3}(?:\.'.$digit.'{1,2})?$/u';
        $western = '/^'.$digit.'{1,3}(?:,'.$digit.'{3})+(?:\.'.$digit.'{1,2})?$/u';

        return preg_match($indian, $bare) === 1 || preg_match($western, $bare) === 1;
    }

    /**
     * The figure with its sign and brackets removed, which grouping says nothing
     * about.
     */
    private function bare(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^\((.+)\)$/u', $value, $inner) === 1) {
            $value = $inner[1];
        }

        return ltrim($value, "-\u{2212} ");
    }
}
