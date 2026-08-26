<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's verdict on one value.
 *
 * The field holds the verdict the calibration uses; these hold every verdict
 * given, which is what makes agreement between reviewers measurable at all.
 */
class ExtractionFieldJudgement extends Model
{
    use HasFactory;

    public const CORRECT = 'correct';

    public const INCORRECT = 'incorrect';

    public const UNSURE = 'unsure';

    protected $fillable = [
        'extraction_field_id',
        'user_id',
        'verdict',
        'note',
        'value_judged',
    ];

    /** @return BelongsTo<ExtractionField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ExtractionField::class, 'extraction_field_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
