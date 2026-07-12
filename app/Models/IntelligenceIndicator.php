<?php

namespace App\Models;

use App\Contracts\Search\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IntelligenceIndicator extends Model implements Searchable
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'detection_payload' => 'array',
            'metadata' => 'array',
            'confidence_score' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): bool => false);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(IntelligenceRule::class, 'intelligence_rule_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CivicIntelligenceRun::class, 'civic_intelligence_run_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(IntelligenceEvidence::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(IntelligenceReview::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(IntelligenceActivity::class);
    }

    public function searchTitle(): string
    {
        return $this->title;
    }

    public function searchDescription(): ?string
    {
        return $this->description;
    }

    public function searchKeywords(): array
    {
        return array_values(array_filter([
            $this->title,
            $this->description,
            $this->module,
            $this->severity,
            $this->status,
            $this->rule?->name,
            $this->rule?->slug,
        ]));
    }

    public function searchRelations(): array
    {
        return [
            'source' => $this->source_type && $this->source_id ? [[
                'type' => $this->source_type,
                'id' => $this->source_id,
                'relationship' => 'indicator_source',
            ]] : [],
            'evidence' => $this->evidence()->get()->map(fn (IntelligenceEvidence $evidence): array => [
                'type' => $evidence->evidenceable_type,
                'id' => $evidence->evidenceable_id,
                'relationship' => 'supporting_evidence',
            ])->all(),
        ];
    }

    public function searchModule(): string
    {
        return 'intelligence';
    }

    public function searchUrl(): string
    {
        return route('admin.intelligence.indicators.show', $this, false);
    }

    public function searchStatus(): ?string
    {
        return $this->status;
    }

    public function searchVisibility(): string
    {
        return 'internal';
    }

    public function searchMetadata(): array
    {
        return [
            'route_module' => 'intelligence',
            'module' => $this->module,
            'severity' => $this->severity,
            'confidence_score' => $this->confidence_score,
            'rule_version' => $this->rule_version,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
        ];
    }
}
