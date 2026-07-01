<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceActivity extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): bool => false);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(IntelligenceIndicator::class, 'intelligence_indicator_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(IntelligenceRule::class, 'intelligence_rule_id');
    }

    public function processingJob(): BelongsTo
    {
        return $this->belongsTo(IntelligenceProcessingJob::class, 'intelligence_processing_job_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
