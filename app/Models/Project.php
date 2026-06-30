<?php

namespace App\Models;

use App\Contracts\Search\Searchable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_code',
    'name',
    'short_name',
    'slug',
    'description',
    'agency_id',
    'parent_id',
    'project_category_id',
    'project_status_id',
    'project_priority_id',
    'funding_source_id',
    'fiscal_year_id',
    'country_id',
    'division_id',
    'district_id',
    'upazila_id',
    'union_id',
    'ward_id',
    'progress_percentage',
    'planned_start_date',
    'actual_start_date',
    'planned_end_date',
    'actual_end_date',
    'latitude',
    'longitude',
    'geojson',
    'featured_image_path',
    'is_public',
    'is_active',
    'archived_at',
    'created_by',
    'updated_by',
])]
class Project extends Model implements Searchable
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date',
            'actual_start_date' => 'date',
            'planned_end_date' => 'date',
            'actual_end_date' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geojson' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'project_category_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'project_status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(ProjectPriority::class, 'project_priority_id');
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(FundingSource::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function upazila(): BelongsTo
    {
        return $this->belongsTo(Upazila::class);
    }

    public function union(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnion::class, 'union_id');
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasMany<ProjectActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class)->latest();
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function tenders(): HasMany
    {
        return $this->hasMany(Tender::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function searchTitle(): string
    {
        return $this->name;
    }

    public function searchDescription(): ?string
    {
        return $this->description;
    }

    public function searchKeywords(): array
    {
        return array_values(array_filter([
            $this->project_code,
            $this->name,
            $this->short_name,
            $this->slug,
            $this->agency?->name,
            $this->category?->name,
            $this->status?->name,
            $this->priority?->name,
            $this->fundingSource?->name,
            $this->fiscalYear?->name,
        ]));
    }

    public function searchRelations(): array
    {
        return [
            'agency' => $this->agency_id ? [['type' => Agency::class, 'id' => $this->agency_id, 'title' => $this->agency?->name]] : [],
            'budgets' => $this->budgets()->limit(10)->get(['id'])->map(fn (Budget $budget): array => ['type' => Budget::class, 'id' => $budget->id])->all(),
            'procurement' => $this->tenders()->limit(10)->get(['id', 'title'])->map(fn (Tender $tender): array => ['type' => Tender::class, 'id' => $tender->id, 'title' => $tender->title])->all(),
            'geography' => array_values(array_filter([
                $this->country_id ? ['type' => Country::class, 'id' => $this->country_id, 'title' => $this->country?->name] : null,
                $this->division_id ? ['type' => Division::class, 'id' => $this->division_id, 'title' => $this->division?->name] : null,
                $this->district_id ? ['type' => District::class, 'id' => $this->district_id, 'title' => $this->district?->name] : null,
            ])),
        ];
    }

    public function searchModule(): string
    {
        return 'projects';
    }

    public function searchUrl(): string
    {
        return route('admin.projects.show', $this, false);
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
            'route_module' => 'projects',
            'project_code' => $this->project_code,
            'progress_percentage' => $this->progress_percentage,
            'agency_id' => $this->agency_id,
            'fiscal_year_id' => $this->fiscal_year_id,
        ];
    }
}
