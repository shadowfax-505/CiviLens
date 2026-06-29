<?php

namespace App\Models;

use Database\Factories\TenderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_id',
    'budget_id',
    'agency_id',
    'procurement_method_id',
    'tender_category_id',
    'tender_status_id',
    'tender_number',
    'title',
    'slug',
    'description',
    'published_at',
    'closing_at',
    'closed_at',
    'is_public',
    'is_active',
    'archived_at',
])]
class Tender extends Model
{
    /** @use HasFactory<TenderFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'closing_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(ProcurementMethod::class, 'procurement_method_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TenderCategory::class, 'tender_category_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TenderStatus::class, 'tender_status_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(TenderLot::class)->orderBy('sort_order');
    }

    public function bidSubmissions(): HasMany
    {
        return $this->hasMany(BidSubmission::class)->latest('submitted_at');
    }

    public function evaluationCommittees(): HasMany
    {
        return $this->hasMany(EvaluationCommittee::class);
    }

    public function evaluationCriteria(): HasMany
    {
        return $this->hasMany(EvaluationCriterion::class)->orderBy('sort_order');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class)->latest('awarded_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProcurementActivity::class)->latest();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
