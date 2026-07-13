<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'engine_version',
    'status',
    'triggered_by',
    'started_at',
    'completed_at',
    'rules_executed',
    'indicators_created',
    'threshold_snapshot',
    'summary_payload',
    'notes',
])]
class CivicIntelligenceRun extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'threshold_snapshot' => 'array',
            'summary_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CivicIntelligenceRun $run): void {
            $run->uuid ??= (string) Str::uuid();
        });

        static::deleting(fn (): bool => false);
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(IntelligenceIndicator::class);
    }
}
