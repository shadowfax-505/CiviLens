<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['analytics_snapshot_period_id', 'dashboard', 'snapshot_date', 'filter_hash', 'filters', 'metrics', 'charts', 'insights', 'generated_by', 'generated_at'])]
class AnalyticsSnapshot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'filters' => 'array',
            'metrics' => 'array',
            'charts' => 'array',
            'insights' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AnalyticsSnapshot $snapshot): void {
            if (! $snapshot->filter_hash) {
                $snapshot->filter_hash = hash('sha256', json_encode($snapshot->filters ?? [], JSON_THROW_ON_ERROR));
            }
        });

        static::deleting(fn (): bool => false);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AnalyticsSnapshotPeriod::class, 'analytics_snapshot_period_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
