<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One provision of an instrument, and its text.
 *
 * `provision_key` is what a citation matches on: type, number and sub-provisions
 * flattened and case-folded, so 'article-102-2-a-ii' is one key however the
 * judgment happened to punctuate it.
 *
 * Matching a citation to one of these is a match between two strings. It says
 * nothing about whether the citation was read correctly off the page, which is
 * the only question this project's guarantee is about, and which for statutory
 * references has never been asked of a reviewer (ADR-024).
 */
class StatuteProvision extends Model
{
    protected $fillable = [
        'statute_id',
        'provision_type',
        'provision_number',
        'provision_label',
        'provision_key',
        'heading',
        'text',
        'text_sha256',
        'source_url',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Statute, self> */
    public function statute(): BelongsTo
    {
        return $this->belongsTo(Statute::class);
    }
}
