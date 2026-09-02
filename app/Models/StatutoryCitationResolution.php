<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one `statutory_reference` value was parsed into, and what it matched.
 *
 * Deliberately has no decision, alpha or basis. A resolution is a match and
 * never a guarantee: the stratum these values belong to has no labels, so no
 * threshold exists to apply to one and every one of them is a candidate
 * (ADR-024). Keeping this in its own table is what makes that structural rather
 * than a rule somebody has to remember — the column that decides what publishes
 * lives on `extraction_fields` and this layer never writes it.
 *
 * A row exists for a value the parser refused, too. 912 of these values cite
 * "the Code" and the corpus holds two of those; recording the refusal and its
 * reason is how that stays a countable fact instead of a silent gap.
 */
class StatutoryCitationResolution extends Model
{
    /** The parser found nothing it could resolve; `reject_reason` says what stopped it. */
    public const STATUS_UNPARSED = 'unparsed';

    /** Parsed, and no instrument with that key is held. */
    public const STATUS_UNMATCHED = 'unmatched';

    /** The instrument is held and this provision of it is not. */
    public const STATUS_INSTRUMENT = 'instrument';

    /** Instrument and provision both held. The strongest thing this layer says. */
    public const STATUS_PROVISION = 'provision';

    protected $fillable = [
        'extraction_field_id',
        'raw_value',
        'parser_version',
        'instrument_name',
        'instrument_key',
        'instrument_year',
        'provision_type',
        'provision_number',
        'provision_label',
        'sub_provisions',
        'match_key',
        'reject_reason',
        'statute_id',
        'statute_provision_id',
        'match_status',
        'matched_at',
    ];

    protected function casts(): array
    {
        return [
            'instrument_year' => 'integer',
            'sub_provisions' => 'array',
            'matched_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ExtractionField, self> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ExtractionField::class, 'extraction_field_id');
    }

    /** @return BelongsTo<Statute, self> */
    public function statute(): BelongsTo
    {
        return $this->belongsTo(Statute::class);
    }

    /** @return BelongsTo<StatuteProvision, self> */
    public function provision(): BelongsTo
    {
        return $this->belongsTo(StatuteProvision::class, 'statute_provision_id');
    }
}
