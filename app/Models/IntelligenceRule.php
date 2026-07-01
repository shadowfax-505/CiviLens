<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntelligenceRule extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'thresholds' => 'array',
            'configuration' => 'array',
            'is_active' => 'boolean',
            'last_executed_at' => 'datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(IntelligenceRuleType::class, 'intelligence_rule_type_id');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(IntelligenceIndicator::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(IntelligenceRuleAudit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
