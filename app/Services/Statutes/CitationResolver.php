<?php

namespace App\Services\Statutes;

use App\Models\ExtractionField;
use App\Models\Statute;
use App\Models\StatuteProvision;
use App\Models\StatutoryCitationResolution;
use Illuminate\Database\Eloquent\Collection;

/**
 * Parses stored `statutory_reference` values and records what each one matched.
 *
 * The two halves are kept apart on purpose. The parser is a pure function of the
 * string, so its output is reproducible from the value alone and can be
 * re-derived when the parser changes. Matching depends on what statute text
 * happens to be held, which will change as text arrives, and its answers are
 * therefore about the corpus rather than about the citation.
 *
 * **A match is not a certification.** ADR-024: the stratum
 * `supremecourt-bangladesh | en | statutory_reference` holds 7,780 values and
 * zero labels, so there is no threshold to apply and every one is a candidate.
 * This class never writes `extraction_fields` — not `decision`, not anything —
 * and the schema it writes into has no column that could hold a level.
 */
class CitationResolver
{
    public function __construct(private readonly StatutoryCitationParser $parser) {}

    /**
     * Parse one value and record what it matched, replacing any earlier parse.
     */
    public function resolveField(ExtractionField $field): StatutoryCitationResolution
    {
        $raw = (string) $field->extracted_value;
        $citation = $this->parser->parse($raw);

        return StatutoryCitationResolution::query()->updateOrCreate(
            ['extraction_field_id' => $field->getKey()],
            $this->attributes($raw, $citation),
        );
    }

    /**
     * Parse every value of a kind and count the outcomes, writing nothing.
     *
     * The runbook's rule: anything that changes stored data reports first and
     * acts only with `--apply`. An operator should be able to see that 84.3% of
     * the corpus resolves and what the other 15.7% is, before a row is written.
     *
     * @return array{
     *     read: int,
     *     provision: int,
     *     instrument: int,
     *     unmatched: int,
     *     unparsed: int,
     *     reasons: array<string, int>,
     * }
     */
    public function previewKind(string $fieldKey = 'statutory_reference', int $chunk = 500): array
    {
        return $this->tally($fieldKey, $chunk, persist: false);
    }

    /**
     * Parse every value of a kind and record each resolution.
     *
     * @return array{
     *     read: int,
     *     provision: int,
     *     instrument: int,
     *     unmatched: int,
     *     unparsed: int,
     *     reasons: array<string, int>,
     * }
     */
    public function resolveKind(string $fieldKey = 'statutory_reference', int $chunk = 500): array
    {
        return $this->tally($fieldKey, $chunk, persist: true);
    }

    /**
     * Walk a kind in chunks, counting what each value came to.
     *
     * Chunked and column-limited because this pipeline has lost four runs to
     * loading a collection whole, and 7,874 rows carrying their page text is
     * exactly that shape of mistake. Only the two columns a parse needs are
     * selected, and `chunkById` holds one page of rows at a time.
     *
     * @return array{
     *     read: int,
     *     provision: int,
     *     instrument: int,
     *     unmatched: int,
     *     unparsed: int,
     *     reasons: array<string, int>,
     * }
     */
    private function tally(string $fieldKey, int $chunk, bool $persist): array
    {
        $read = 0;
        $provision = 0;
        $instrument = 0;
        $unmatched = 0;
        $unparsed = 0;

        /** @var array<string, int> $reasons */
        $reasons = [];

        ExtractionField::query()
            ->select(['id', 'extracted_value'])
            ->where('field_key', $fieldKey)
            ->whereNotNull('extracted_value')
            ->chunkById(max(1, $chunk), function (Collection $fields) use (
                $persist, &$read, &$provision, &$instrument, &$unmatched, &$unparsed, &$reasons
            ): void {
                foreach ($fields as $field) {
                    $raw = (string) $field->extracted_value;
                    $attributes = $this->attributes($raw, $this->parser->parse($raw));

                    if ($persist) {
                        StatutoryCitationResolution::query()->updateOrCreate(
                            ['extraction_field_id' => $field->getKey()],
                            $attributes,
                        );
                    }

                    $read++;

                    match ((string) $attributes['match_status']) {
                        StatutoryCitationResolution::STATUS_PROVISION => $provision++,
                        StatutoryCitationResolution::STATUS_INSTRUMENT => $instrument++,
                        StatutoryCitationResolution::STATUS_UNMATCHED => $unmatched++,
                        default => $unparsed++,
                    };

                    $reason = $attributes['reject_reason'];

                    if (is_string($reason)) {
                        $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
                    }
                }
            });

        return [
            'read' => $read,
            'provision' => $provision,
            'instrument' => $instrument,
            'unmatched' => $unmatched,
            'unparsed' => $unparsed,
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(string $raw, ParsedCitation $citation): array
    {
        $attributes = [
            'raw_value' => $raw,
            'parser_version' => StatutoryCitationParser::VERSION,
            'instrument_name' => $citation->instrumentName,
            'instrument_key' => $citation->instrumentKey,
            'instrument_year' => $citation->instrumentYear,
            'provision_type' => $citation->provisionType,
            'provision_number' => $citation->provisionNumber,
            'provision_label' => $citation->provisionLabel(),
            'sub_provisions' => $citation->subProvisions === [] ? null : $citation->subProvisions,
            'match_key' => $citation->matchKey(),
            'reject_reason' => $citation->reason,
            'statute_id' => null,
            'statute_provision_id' => null,
            'match_status' => StatutoryCitationResolution::STATUS_UNPARSED,
            'matched_at' => null,
        ];

        if (! $citation->isResolvable()) {
            return $attributes;
        }

        $statute = $this->findStatute((string) $citation->instrumentKey);

        if (! $statute instanceof Statute) {
            // Parsed and nothing to look it up in. This is the state that says
            // which instrument to go and fetch, which is why it is kept apart
            // from a parse failure rather than folded into one.
            $attributes['match_status'] = StatutoryCitationResolution::STATUS_UNMATCHED;

            return $attributes;
        }

        $attributes['statute_id'] = $statute->getKey();
        $attributes['match_status'] = StatutoryCitationResolution::STATUS_INSTRUMENT;
        $attributes['matched_at'] = now();

        $provision = StatuteProvision::query()
            ->where('statute_id', $statute->getKey())
            ->where('provision_key', $citation->provisionKey())
            ->first();

        if ($provision instanceof StatuteProvision) {
            $attributes['statute_provision_id'] = $provision->getKey();
            $attributes['match_status'] = StatutoryCitationResolution::STATUS_PROVISION;
        }

        return $attributes;
    }

    /**
     * The instrument for a key, by its own key or by one it is known as.
     *
     * The second lookup is what lets "Section 100 of the CPC" reach the Code of
     * Civil Procedure without the parser having decided that CPC means that Code.
     * The expansion is a curated fact on the statute row, so it can be read,
     * argued with and corrected; a constant inside a parser could not be.
     */
    private function findStatute(string $key): ?Statute
    {
        $statute = Statute::query()->where('instrument_key', $key)->first();

        if ($statute instanceof Statute) {
            return $statute;
        }

        $statute = Statute::query()->whereJsonContains('known_as', $key)->first();

        return $statute instanceof Statute ? $statute : null;
    }
}
