<?php

namespace App\Models;

use App\Contracts\Search\Searchable;
use Database\Factories\ProcurementPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'plan_number',
    'title',
    'agency_id',
    'budget_id',
    'project_id',
    'fiscal_year_id',
    'funding_source_id',
    'procurement_method_id',
    'estimated_value',
    'priority',
    'planned_start_date',
    'planned_award_date',
    'planned_completion_date',
    'status',
    'approved_by',
    'approved_at',
    'approval_notes',
])]
class ProcurementPlan extends Model implements Searchable
{
    /** @use HasFactory<ProcurementPlanFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'planned_start_date' => 'date',
            'planned_award_date' => 'date',
            'planned_completion_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(FundingSource::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(ProcurementMethod::class, 'procurement_method_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProcurementPlanActivity::class)->latest();
    }

    public function searchTitle(): string
    {
        return $this->title;
    }

    public function searchDescription(): ?string
    {
        return $this->approval_notes;
    }

    public function searchKeywords(): array
    {
        return array_values(array_filter([
            $this->plan_number,
            $this->title,
            $this->agency?->name,
            $this->project?->name,
            $this->method?->name,
            $this->priority,
            $this->status,
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
        return route('admin.procurement.plans.show', $this, false);
    }

    public function searchStatus(): ?string
    {
        return $this->status;
    }

    public function searchVisibility(): string
    {
        return $this->status === 'approved' ? 'internal' : 'private';
    }

    public function searchMetadata(): array
    {
        return [
            'route_module' => 'procurement_plans',
            'plan_number' => $this->plan_number,
            'agency_id' => $this->agency_id,
            'project_id' => $this->project_id,
            'budget_id' => $this->budget_id,
            'estimated_value' => $this->estimated_value,
        ];
    }
}
