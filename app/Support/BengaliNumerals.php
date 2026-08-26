<?php

namespace App\Support;

/**
 * Bengali and Latin digits treated as one alphabet.
 *
 * Legacy-font pages in this corpus store Bengali numerals as the Latin bytes
 * that happen to render them, so `216.9` and `২১৬.৯` are one figure written two
 * ways. Anything comparing figures has to know that: the second read would
 * otherwise call every native page a disagreement, and a search for a figure a
 * reader can see on the page would return nothing because the page stores it in
 * the other alphabet.
 */
class BengaliNumerals
{
    private const BENGALI = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    private const LATIN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public function toLatin(string $text): string
    {
        return str_replace(self::BENGALI, self::LATIN, $text);
    }

    public function toBengali(string $text): string
    {
        return str_replace(self::LATIN, self::BENGALI, $text);
    }

    /**
     * A figure reduced to what two readings of it must share.
     *
     * Digits in one alphabet, and the punctuation that can carry meaning kept:
     * a separator in the wrong place is a hundredfold error, and dropping it
     * here would make two different figures look identical.
     */
    public function canonical(string $text): string
    {
        return (string) preg_replace('/[^0-9.,()\-]/u', '', $this->toLatin($text));
    }

    /**
     * The digits alone, for asking whether two figures are the same number
     * however they are punctuated. `৫,১৪,০৫১` and `৫১৪,০৫১` are one amount
     * grouped two ways, and a reader searching for either means the same thing.
     */
    public function digitsOnly(string $text): string
    {
        return (string) preg_replace('/\D/', '', $this->toLatin($text));
    }

    public function containsDigits(string $text): bool
    {
        return $this->digitsOnly($text) !== '';
    }
}
