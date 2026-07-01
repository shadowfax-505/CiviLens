<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['analytics_alert_rule_id', 'title', 'message', 'severity', 'status', 'triggered_value', 'context', 'triggered_at', 'acknowledged_by', 'acknowledged_at', 'resolved_at'])]
class AnalyticsAlert extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'triggered_value' => 'decimal:4',
            'context' => 'array',
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): bool => false);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AnalyticsAlertRule::class, 'analytics_alert_rule_id');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
