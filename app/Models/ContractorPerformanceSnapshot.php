<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorPerformanceSnapshot extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'delay_days' => 'integer',
            'final_cost' => 'float',
            'cost_variance' => 'float',
            'quality_rating' => 'integer',
            'agency_evaluation' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): bool => false);
    }

    public function contractorProfile(): BelongsTo
    {
        return $this->belongsTo(ContractorProfile::class);
    }
}
