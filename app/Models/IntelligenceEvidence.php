<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IntelligenceEvidence extends Model
{
    use HasFactory;

    protected $table = 'intelligence_evidence';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'weight' => 'integer',
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

    public function evidenceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
