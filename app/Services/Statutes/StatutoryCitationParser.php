<?php

namespace App\Services\Statutes;

/**
 * Turns a raw `statutory_reference` value into the pieces a statute lookup needs.
 *
 * Every pattern here was written against the 7,874 values actually stored, not
 * against what a citation ought to look like. That order matters in this
 * pipeline: the `amount` pattern needed four corrections because it was invented
 * first, and two multi-word field kinds were added without checking the value
 * locator could span them, so they located 0% of the time.
 *
 * What the corpus turned out to hold, measured over all 7,874 (7,780 of them the
 * Supreme Court's English stratum, 93 cbad, 48 supremecourt mixed, 1 imed):
 *
 * | shape | count |
 * | --- | --- |
 * | `<Section\|Article\|Rule\|Order> <n> of the <instrument>` | 7,874 — all of them |
 * | carries a sub-provision, `102(2)(a)` | 2,118 |
 * | carries a letter suffix, `265H`, `561A` | 340 |
 * | states an instrument year anywhere in the tail | 427 |
 * | instrument is a bare anaphor, "of the Code" | 881 |
 * | instrument phrase names no instrument, "State Acquisition" | 505 |
 *
 * Every value begins with its provision keyword and every one contains "of the",
 * because the candidate pattern that produced them requires both. The parser
 * does not rely on that: `Article 111` and `rule 98 of PPR 2008` both go through
 * it, because the corpus is the evidence for what these strings look like and not
 * a specification for what may be handed in.
 *
 * The messiness is in the tail rather than the head. The candidate pattern ends
 * a match at `[,;.]` or at " and", so it truncates the instrument mid-name — 148
 * values read "State Acquisition" where the page said "State Acquisition and
 * Tenancy Act", 28 read "Cr.P" where the page said "Cr.P.C.", and "Code of
 * Criminal Procedure, 1898" arrives with the year already gone. Where the
 * lookahead did not fire, prose ran on instead: "Code of Civil Procedure this
 * Rule" (93), "Act, 1881 in filing the case" (42). So the instrument name has to
 * be found inside the tail rather than assumed to be the whole of it.
 *
 * Nothing this class produces is a certification. ADR-024: the stratum
 * `supremecourt-bangladesh | en | statutory_reference` has 7,780 values and no
 * labels, so there is no threshold to apply and every one of them is a
 * candidate. A parsed citation is a parsed string.
 */
class StatutoryCitationParser
{
    /**
     * The reader that produced a stored parse, recorded alongside it.
     *
     * The runbook's rule for scores — a measurement taken against characters that
     * have since changed is a measurement of the wrong thing — applies just as
     * much to a parse. Bumping this is what lets a re-parse be told from a stale
     * one instead of both looking like results.
     */
    public const VERSION = 'statutes-parser-1';

    /**
     * The provision keyword, its number, and any bracketed sub-provisions.
     *
     * The keyword is case-folded by an inline group so the number's letter suffix
     * stays case-sensitive. That is not fussiness: with `/i` over the whole
     * pattern, `[A-Z]{0,2}` after `\s*` matches the "of" in "Section 39 of" and
     * the instrument clause then fails to parse on 69% of the corpus. It did,
     * while this was being written.
     *
     * All 340 letter suffixes in the corpus are upper case (`265H`, `561A`,
     * `111F`), so the suffix class is upper case and a lower-case one is left
     * unread rather than half-read.
     */
    private const HEAD = '/^(?:(?i:the)\s+)?(?<type>(?i:articles?|sections?|sub[-\s]?sections?|rules?|sub[-\s]?rules?|orders?|clauses?))\s*\.?\s*(?<number>\d{1,4}[A-Z]{0,2})(?![A-Za-z])(?<subs>(?:\s*\([^)\n]{1,12}\))*)/u';

    /** "of the X", "under the X", "of X" — 7,874 of 7,874 use "of the". */
    private const INSTRUMENT_CLAUSE = '/^\s*(?i:of|under)\s+(?:(?i:the)\s+)?(?<rest>.+)$/u';

    private const YEAR = '/^(?:1[6-9]\d{2}|20\d{2})$/';

    /**
     * Words that may sit inside an instrument's name without ending it.
     *
     * "Code **of** Civil Procedure", "Government **of** India Act", "Carriage
     * **of** Goods **by** Sea Act", "State Acquisition **and** Tenancy Act".
     *
     * @var list<string>
     */
    private const CONNECTIVES = ['of', 'the', 'and', 'to', 'by', 'for', 'in'];

    /**
     * The connectives that may hang a qualifier off the instrument noun.
     *
     * "and" is missing on purpose, and only from this side of the noun. It
     * belongs inside a name that reaches the noun through it — "State
     * Acquisition **and** Tenancy Act" — and after the noun it is the sentence
     * carrying on: "Section 100 of the Act and the Seventh Schedule to it show
     * how the division has" is a citation of the Act, and reading it as an
     * instrument called "Act and the Seventh Schedule" would key one citation to
     * an instrument that does not exist. So is "the", for the reason
     * nameShapedSpan() gives.
     *
     * @var list<string>
     */
    private const QUALIFIER_OPENERS = ['of', 'to', 'by', 'for', 'in'];

    /**
     * The nouns that make a phrase the name of an instrument.
     *
     * A phrase without one of these is not an instrument name — which is what
     * separates "Prevention of Corruption Act" from "Articles of Association"
     * (29 values, a company's constitution and not a statute) and from
     * "Comptroller" (4, where OCR or the truncation took the rest).
     *
     * @var list<string>
     */
    private const INSTRUMENT_NOUNS = [
        'act', 'acts', 'ain', 'code', 'codes', 'rule', 'rules', 'ordinance',
        'ordinances', 'constitution', 'order', 'orders', 'regulation',
        'regulations', 'bidhi', 'bidhimala', 'adhyadesh', 'sanghbidhan',
        'statute', 'statutes',
    ];

    /**
     * The one bare instrument noun that still denotes a single instrument.
     *
     * "of the Code" is ambiguous and refused: this corpus cites the Code of
     * Civil Procedure 1,099 times and the Code of Criminal Procedure 385 times,
     * in the same documents, so there is no default. "of the Constitution" is
     * not in that position — Bangladesh has one, and every one of the 26 foreign
     * constitutions in the corpus is written out ("Constitution of India" 15,
     * "Indian Constitution" 13, "Constitution of Pakistan" 3, one each for
     * Nepal, Sri Lanka, Singapore and the Maldives). The 983 bare ones denote
     * uniquely, and calling them anaphoric would refuse the single most cited
     * instrument in the corpus on a symmetry that the data does not have.
     *
     * @var list<string>
     */
    private const STANDALONE_INSTRUMENTS = ['constitution'];

    public function parse(string $raw): ParsedCitation
    {
        $text = $this->normalise($raw);

        if (preg_match(self::HEAD, $text, $head) !== 1) {
            // No number to read. Roman-numeral provisions ("Order XLI Rule 1")
            // land here and are refused rather than approximated; the corpus
            // holds none, because the candidate pattern cannot produce one.
            return ParsedCitation::rejected($raw, ParsedCitation::REASON_NO_PROVISION);
        }

        $type = $this->provisionType($head['type']);
        $number = $head['number'];
        $subs = $this->subProvisions($head['subs']);
        $tail = mb_substr($text, mb_strlen($head[0]));

        if (preg_match(self::INSTRUMENT_CLAUSE, $tail, $clause) !== 1) {
            return ParsedCitation::rejected(
                $raw, ParsedCitation::REASON_NO_INSTRUMENT, $type, $number, $subs,
            );
        }

        $span = $this->nameShapedSpan($clause['rest']);

        if ($span === []) {
            return ParsedCitation::rejected(
                $raw, ParsedCitation::REASON_NO_INSTRUMENT, $type, $number, $subs,
            );
        }

        $name = $this->instrumentName($span);

        if ($name === null) {
            return ParsedCitation::rejected(
                $raw, ParsedCitation::REASON_UNNAMED_INSTRUMENT, $type, $number, $subs,
                implode(' ', $span),
            );
        }

        $year = $this->instrumentYear($name);

        if ($this->isAnaphoric($name)) {
            return ParsedCitation::rejected(
                $raw, ParsedCitation::REASON_ANAPHORIC_INSTRUMENT, $type, $number, $subs,
                $name, $year,
            );
        }

        return ParsedCitation::resolvable(
            $raw, $type, $number, $subs, $name, $this->instrumentKey($name), $year,
        );
    }

    /**
     * Whitespace and the artefacts of reading a value off a page.
     *
     * The stored values carry no newline, because the candidate generator joins a
     * citation that runs across a line break before storing it — a citation that
     * kept its newline would be unlocatable by construction. This still repairs
     * them, because the parser is also handed page text directly by tests and by
     * anything that reads a document before the generator has.
     */
    private function normalise(string $value): string
    {
        // A hyphen at a line end is the page breaking a word, not part of it.
        $value = (string) preg_replace('/(\p{L})-\s*\R\s*(\p{L})/u', '$1$2', $value);
        $value = str_replace(["\u{00ad}", "\u{200b}", "\u{feff}"], '', $value);
        $value = (string) preg_replace('/[\s\x{00a0}]+/u', ' ', $value);

        return trim($value);
    }

    /** 'Sub-Sections' and 'sub section' are one type; 'Articles' and 'Article' are one type. */
    private function provisionType(string $keyword): string
    {
        $type = mb_strtolower(trim($keyword));
        $type = (string) preg_replace('/s$/', '', $type);

        return (string) preg_replace('/[\s-]+/', '_', $type);
    }

    /**
     * '(2)(a)' gives ['2', 'a'].
     *
     * 2,118 values carry one. Kept as written, including the transliterated
     * Bengali clauses the corpus uses — '(ka)' 27 times and '(Ka)' 21 — which the
     * provision key case-folds so they are one provision rather than two.
     *
     * @return list<string>
     */
    private function subProvisions(string $captured): array
    {
        if (preg_match_all('/\(([^)]*)\)/u', $captured, $matches) === 0) {
            return [];
        }

        $parts = [];

        foreach ($matches[1] as $part) {
            $part = trim($part);

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * The leading run of the tail that could still be part of a name.
     *
     * Stops at the first word that reads as prose resuming. "Code of Civil
     * Procedure **this** Rule" stops at "this"; "Companies Act **read** with
     * Rule 75" stops at "read".
     *
     * "the" is the one connective that needs a rule of its own. It belongs inside
     * a name only behind another connective — "Constitution of **the** Peoples
     * Republic" — because a bare "X the Y" is a dropped comma and the prose
     * carrying on. Without that restriction 12 values grew instrument names like
     * "Constitution the High Court Division" and "Constitution the Chief
     * Justice", and each would have keyed as its own instrument.
     *
     * @return list<string>
     */
    private function nameShapedSpan(string $tail): array
    {
        $tokens = $tail === '' ? [] : explode(' ', $tail);
        $kept = [];

        foreach ($tokens as $i => $token) {
            if ($this->isCapitalised($token)) {
                $kept[] = $token;

                continue;
            }

            $bare = $this->bare($token);

            if (! in_array($bare, self::CONNECTIVES, true)) {
                break;
            }

            if ($bare === 'the') {
                $previous = $i > 0 ? $this->bare($tokens[$i - 1]) : '';

                if ($previous === 'the' || ! in_array($previous, self::CONNECTIVES, true)) {
                    break;
                }
            }

            // A connective earns its place only if a name continues after it.
            $ahead = $i + 1;

            if (($tokens[$ahead] ?? null) !== null && $this->bare($tokens[$ahead]) === 'the') {
                $ahead++;
            }

            if (! $this->isCapitalised($tokens[$ahead] ?? '')) {
                break;
            }

            $kept[] = $token;
        }

        return $this->trimConnectives($kept);
    }

    /**
     * The instrument's name inside the span, or null when the span holds none.
     *
     * Anchored on the first instrument noun, then extended rightwards only
     * through a qualifier that a connective or a year opens. That is what keeps
     * "Constitution of Pakistan 1962" whole while cutting "Constitution Rule
     * Nisi" back to "Constitution": a capitalised word straight after the noun,
     * with no connective joining it, is the sentence and not the title.
     *
     * The noun is the *first* in the span rather than the last, so "Companies Act
     * read with Rule 75 of the Companies Rules" resolves against the Companies
     * Act it opens with rather than the Rules it mentions in passing.
     *
     * @param  list<string>  $span
     */
    private function instrumentName(array $span): ?string
    {
        $noun = null;

        foreach ($span as $i => $token) {
            if (in_array($this->bare($token), self::INSTRUMENT_NOUNS, true)) {
                $noun = $i;

                break;
            }
        }

        if ($noun === null) {
            // An abbreviation is a name, so "CrPC" (30 values), "CPC" (23),
            // "PPR" (7) and "RPO" (10) are read as instruments and keyed on
            // exactly what the page said. Expanding them here would be the
            // guess this layer refuses to make; a statute row carries its own
            // known-as list, so the expansion lives where a person can see and
            // correct it. 34 of these are truncations — "Cr.P", "S.A.T" — and
            // they key faithfully and simply never match, which is a match not
            // found rather than a match invented.
            if (! $this->isAbbreviation($span[0])) {
                return null;
            }

            if (count($span) === 1) {
                return $span[0];
            }

            // An initialism may carry its year and nothing else, so "PPR 2008"
            // is the Public Procurement Rules and "ICSID Convention" is still
            // refused.
            return count($span) === 2 && preg_match(self::YEAR, $this->bare($span[1])) === 1
                ? $span[0].' '.$span[1]
                : null;
        }

        $end = $noun;
        $opened = false;
        $i = $noun + 1;
        $count = count($span);

        while ($i < $count) {
            $token = $span[$i];
            $bare = $this->bare($token);

            if (preg_match(self::YEAR, $bare) === 1) {
                $end = $i;
                $opened = true;
                $i++;

                continue;
            }

            $isOpener = in_array($bare, self::QUALIFIER_OPENERS, true);

            if (! $opened) {
                if (! $isOpener) {
                    break;
                }

                $opened = true;
                $end = $i;
                $i++;

                continue;
            }

            if ($bare === 'the') {
                // "of **the** Peoples Republic" carries a name on; a capitalised
                // "The" that no connective introduced is a new sentence. One
                // stored value reads "Section 164 of the Code of Criminal
                // Procedure The Code of Criminal Procedure", and read the other
                // way it keys to an instrument named twice over.
                if (! in_array($this->bare($span[$i - 1]), self::QUALIFIER_OPENERS, true)) {
                    break;
                }

                $end = $i;
                $i++;

                continue;
            }

            if ($isOpener || $this->isCapitalised($token)) {
                $end = $i;
                $i++;

                continue;
            }

            break;
        }

        $name = $this->trimConnectives(array_slice($span, 0, $end + 1));

        return $name === [] ? null : implode(' ', $name);
    }

    /**
     * Whether the name is a bare noun pointing back at something named earlier.
     *
     * "the Code", "the Rules", "the Act, 1881" — one instrument noun and nothing
     * naming which. 881 values, and the year some of them carry is kept on the
     * rejection because it is a real fact about the citation even when the
     * instrument is not recoverable from the value alone.
     */
    private function isAnaphoric(string $name): bool
    {
        $core = [];

        foreach (explode(' ', $name) as $token) {
            $bare = $this->bare($token);

            if ($bare === '' || in_array($bare, self::CONNECTIVES, true)) {
                continue;
            }

            if (preg_match('/^[\d,]+$/', $bare) === 1) {
                continue;
            }

            $core[] = $bare;
        }

        return count($core) === 1
            && in_array($core[0], self::INSTRUMENT_NOUNS, true)
            && ! in_array($core[0], self::STANDALONE_INSTRUMENTS, true);
    }

    /**
     * The year stated in the name, where one is.
     *
     * 427 of 7,874 values state one anywhere, and the candidate pattern's comma
     * lookahead is why: "Code of Criminal Procedure, 1898" is stored without it.
     * So a year is a bonus for disambiguating instruments that share a title, and
     * never a component of the key — see ParsedCitation::matchKey().
     */
    private function instrumentYear(string $name): ?int
    {
        foreach (explode(' ', $name) as $token) {
            $bare = $this->bare($token);

            if (preg_match(self::YEAR, $bare) === 1) {
                return (int) $bare;
            }
        }

        return null;
    }

    /**
     * The name flattened for matching: 'code-of-civil-procedure'.
     *
     * The year comes out, so "Artha Rin Adalat Ain" (48 values) and "Artha Rin
     * Adalat Ain 2003" (1) are one key rather than two that each match half the
     * corpus.
     */
    private function instrumentKey(string $name): string
    {
        $withoutYear = (string) preg_replace('/\b(?:1[6-9]\d{2}|20\d{2})\b/', ' ', $name);
        $slug = mb_strtolower($withoutYear);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }

    /** A word the page set with a capital, or a figure. */
    private function isCapitalised(string $token): bool
    {
        return preg_match('/^[A-Z0-9]/u', $token) === 1;
    }

    /**
     * An initialism: 2 to 8 letters, at least two of them capital.
     *
     * "CrPC" and "S.R.O" qualify; "Comptroller" and "Acquisition" do not, which
     * is what stops a single truncated ordinary word from being read as the name
     * of an instrument.
     */
    private function isAbbreviation(string $token): bool
    {
        $letters = (string) preg_replace('/[^A-Za-z]/', '', $token);

        if (mb_strlen($letters) < 2 || mb_strlen($letters) > 8) {
            return false;
        }

        return mb_strlen((string) preg_replace('/[^A-Z]/', '', $letters)) >= 2;
    }

    /** A token without the punctuation the page left on it, case-folded. */
    private function bare(string $token): string
    {
        return mb_strtolower(trim($token, ".,;:'\"()"));
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function trimConnectives(array $tokens): array
    {
        while ($tokens !== [] && in_array($this->bare($tokens[count($tokens) - 1]), self::CONNECTIVES, true)) {
            array_pop($tokens);
        }

        return array_values($tokens);
    }
}
