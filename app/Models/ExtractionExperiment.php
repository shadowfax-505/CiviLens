<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractionExperiment extends Model
{
    protected $fillable = [
        'uuid', 'loop', 'corpus', 'iteration', 'config_hash', 'config', 'status',
        'fields', 'correct', 'field_accuracy', 'metrics', 'failure_reason',
        'duration_ms', 'promoted_at', 'promoted_by',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'metrics' => 'array',
            'iteration' => 'integer',
            'fields' => 'integer',
            'correct' => 'integer',
            'field_accuracy' => 'float',
            'duration_ms' => 'integer',
            'promoted_at' => 'immutable_datetime',
        ];
    }

    /**
     * Results are evidence. A recorded measurement cannot be deleted, and once a
     * run has finished the only permitted later change is the human promotion
     * decision -- otherwise a loop could quietly rewrite the record it is judged
     * against.
     */
    protected static function booted(): void
    {
        static::deleting(fn (): bool => false);

        static::updating(function (self $experiment): bool {
            $finished = in_array($experiment->getOriginal('status'), ['completed', 'failed'], true);
            $changed = array_keys($experiment->getDirty());

            if (! $finished) {
                return true;
            }

            return array_diff($changed, ['promoted_at', 'promoted_by', 'updated_at']) === [];
        });
    }

    /** @return BelongsTo<User, self> */
    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }

    public function isPromoted(): bool
    {
        return $this->promoted_at !== null;
    }
}
