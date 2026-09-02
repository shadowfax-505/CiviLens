<?php

namespace App\Services\Statutes;

/**
 * One statutory citation broken into the pieces a lookup needs, or the reason it
 * could not be.
 *
 * A citation that failed to parse is kept rather than dropped. 7,874 of these
 * strings sit in `extraction_fields` and 1,386 of them name no instrument this
 * layer can identify — "Section 151 of the Code", "Section 3A of the
 * Comptroller" — and the useful thing to record about those is that the
 * provision was read and the instrument was not. Dropping them would report a
 * parse rate of 100% over whatever happened to parse.
 *
 * Nothing here is a certification. ADR-024 records that every statutory
 * reference in this corpus is a candidate: the stratum
 * `supremecourt-bangladesh | en | statutory_reference` holds 7,780 values and
 * zero labels, so no threshold exists to apply to one. A citation that matches a
 * stored provision is a match between two strings and carries no bound.
 */
final class ParsedCitation
{
    /** No provision keyword and number could be read at the head of the string. */
    public const REASON_NO_PROVISION = 'no_provision';

    /** A provision was read and the string then stopped. "Article 111". */
    public const REASON_NO_INSTRUMENT = 'no_instrument';

    /**
     * The instrument is a bare noun pointing back at a name given earlier on the
     * page: "of the Code", "of the Rules", "of the Act, 1881". 881 values.
     *
     * These are not resolvable from the value alone and must not be guessed. The
     * corpus cites the Code of Civil Procedure 1,099 times and the Code of
     * Criminal Procedure 385 times, so "the Code" is genuinely ambiguous between
     * two instruments that are both common in the same documents.
     */
    public const REASON_ANAPHORIC_INSTRUMENT = 'anaphoric_instrument';

    /**
     * A phrase was found where the instrument should be, and it names no
     * instrument: "State Acquisition", "Articles of Association", "First
     * Schedule", "Comptroller", "ConstitutiOQ". 505 values.
     *
     * Two distinct causes, both worth keeping apart from a parse success. The
     * generator's own pattern ends a match at " and" (ADR-019's candidate
     * patterns), so "State Acquisition and Tenancy Act" is stored as "State
     * Acquisition" — 148 values whose instrument name was truncated before it
     * reached the database. The rest is OCR damage.
     */
    public const REASON_UNNAMED_INSTRUMENT = 'unnamed_instrument';

    /**
     * @param  list<string>  $subProvisions  '102(2)(a)' gives ['2', 'a'].
     */
    private function __construct(
        public string $raw,
        public ?string $provisionType,
        public ?string $provisionNumber,
        public array $subProvisions,
        public ?string $instrumentName,
        public ?string $instrumentKey,
        public ?int $instrumentYear,
        public ?string $reason,
    ) {}

    /**
     * @param  list<string>  $subProvisions
     */
    public static function resolvable(
        string $raw,
        string $provisionType,
        string $provisionNumber,
        array $subProvisions,
        string $instrumentName,
        string $instrumentKey,
        ?int $instrumentYear,
    ): self {
        return new self(
            $raw,
            $provisionType,
            $provisionNumber,
            $subProvisions,
            $instrumentName,
            $instrumentKey,
            $instrumentYear,
            null,
        );
    }

    /**
     * A string this layer declines to resolve, carrying whatever it did read.
     *
     * @param  list<string>  $subProvisions
     */
    public static function rejected(
        string $raw,
        string $reason,
        ?string $provisionType = null,
        ?string $provisionNumber = null,
        array $subProvisions = [],
        ?string $instrumentName = null,
        ?int $instrumentYear = null,
    ): self {
        return new self(
            $raw,
            $provisionType,
            $provisionNumber,
            $subProvisions,
            $instrumentName,
            null,
            $instrumentYear,
            $reason,
        );
    }

    /**
     * Whether this citation carries enough to look a provision up.
     *
     * An instrument key and a provision. Never a statement about correctness:
     * "Section 21 of the Wadf Ordinance" is resolvable and its instrument is an
     * OCR misreading of Waqf, which is a match that will not be found rather
     * than a match that is wrong.
     */
    public function isResolvable(): bool
    {
        return $this->instrumentKey !== null
            && $this->provisionType !== null
            && $this->provisionNumber !== null;
    }

    /**
     * The provision as a citation states it: '5(2)', '98', '102(2)(a)', '265H'.
     */
    public function provisionLabel(): ?string
    {
        if ($this->provisionNumber === null) {
            return null;
        }

        return $this->provisionNumber.implode('', array_map(
            static fn (string $part): string => '('.$part.')',
            $this->subProvisions,
        ));
    }

    /**
     * The provision flattened for matching: 'rule-11', 'article-102-2-a'.
     *
     * Case-folded, because the corpus writes the same Bengali sub-clause as both
     * '(ka)' (27 values) and '(Ka)' (21), and those are one provision.
     */
    public function provisionKey(): ?string
    {
        if ($this->provisionType === null || $this->provisionNumber === null) {
            return null;
        }

        $parts = array_merge([$this->provisionType, $this->provisionNumber], $this->subProvisions);

        return mb_strtolower(implode('-', $parts));
    }

    /**
     * The key a stored provision is matched on: 'code-of-civil-procedure/rule-11'.
     *
     * The year is deliberately not in this key. The corpus writes the same
     * instrument as "Artha Rin Adalat Ain" (48 values) and "Artha Rin Adalat Ain
     * 2003" (1), and only 427 of 7,874 values state a year at all — the
     * candidate pattern ends a match at a comma, so "Code of Criminal Procedure,
     * 1898" arrives with its year already cut off. Keying on the year would
     * split one instrument into two and match neither. It is carried separately
     * so a matcher can use it to break a tie between instruments that share a
     * title, which is the only thing it is good for here.
     */
    public function matchKey(): ?string
    {
        if (! $this->isResolvable()) {
            return null;
        }

        return $this->instrumentKey.'/'.$this->provisionKey();
    }

    /**
     * @return array{
     *     raw: string,
     *     provision_type: ?string,
     *     provision_number: ?string,
     *     provision_label: ?string,
     *     sub_provisions: list<string>,
     *     instrument_name: ?string,
     *     instrument_key: ?string,
     *     instrument_year: ?int,
     *     match_key: ?string,
     *     reason: ?string,
     * }
     */
    public function toArray(): array
    {
        return [
            'raw' => $this->raw,
            'provision_type' => $this->provisionType,
            'provision_number' => $this->provisionNumber,
            'provision_label' => $this->provisionLabel(),
            'sub_provisions' => $this->subProvisions,
            'instrument_name' => $this->instrumentName,
            'instrument_key' => $this->instrumentKey,
            'instrument_year' => $this->instrumentYear,
            'match_key' => $this->matchKey(),
            'reason' => $this->reason,
        ];
    }
}
