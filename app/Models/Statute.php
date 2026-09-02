<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One instrument — an Act, an Ordinance, a set of Rules, the Constitution.
 *
 * The row a parsed citation's `instrument_key` is looked up against. It holds
 * nothing fetched: `source_url` and `retrieved_at` stay null until a publisher
 * of statute text is registered with its own robots and terms decision, which is
 * a governance step and not part of the parsing layer.
 *
 * `known_as` carries the other keys this instrument is cited by, because those
 * are judgements about Bangladeshi practice — 'cpc' means the Code of Civil
 * Procedure and 'crpc' a different Code — and a judgement belongs in data a
 * person can read and correct, not inside a parser's constants.
 */
class Statute extends Model
{
    protected $fillable = [
        'instrument_key',
        'title',
        'instrument_year',
        'instrument_number',
        'known_as',
        'script',
        'source_url',
        'retrieved_at',
    ];

    protected function casts(): array
    {
        return [
            'instrument_year' => 'integer',
            'known_as' => 'array',
            'retrieved_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<StatuteProvision, self> */
    public function provisions(): HasMany
    {
        return $this->hasMany(StatuteProvision::class);
    }
}
