<?php

namespace App\Models;

use App\Contracts\Search\Searchable;
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
class Tender extends Model implements Searchable
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

    public function plans(): HasMany
    {
        return $this->hasMany(ProcurementPlan::class, 'project_id', 'project_id');
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
            $this->tender_number,
            $this->title,
            $this->slug,
            $this->project?->name,
            $this->budget?->project?->project_code,
            $this->agency?->name,
            $this->method?->name,
            $this->category?->name,
            $this->status?->name,
        ]));
    }

    public function searchRelations(): array
    {
        return [
            'project' => $this->project_id ? [['type' => Project::class, 'id' => $this->project_id, 'title' => $this->project?->name]] : [],
            'budget' => $this->budget_id ? [['type' => Budget::class, 'id' => $this->budget_id, 'title' => $this->budget?->searchTitle()]] : [],
            'agency' => $this->agency_id ? [['type' => Agency::class, 'id' => $this->agency_id, 'title' => $this->agency?->name]] : [],
        ];
    }

    public function searchModule(): string
    {
        return 'procurement';
    }

    public function searchUrl(): string
    {
        return route('admin.procurement.tenders.show', $this, false);
    }

    public function searchStatus(): ?string
    {
        return $this->status?->slug;
    }

    public function searchVisibility(): string
    {
        return $this->is_public ? 'public' : 'internal';
    }

    public function searchMetadata(): array
    {
        return [
            'route_module' => 'tenders',
            'tender_number' => $this->tender_number,
            'project_id' => $this->project_id,
            'budget_id' => $this->budget_id,
            'agency_id' => $this->agency_id,
            'closing_at' => $this->closing_at?->toDateTimeString(),
        ];
    }
}
