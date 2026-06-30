<?php

namespace App\Models;

use App\Contracts\Search\Searchable;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['award_id', 'bid_submission_id', 'project_id', 'budget_id', 'contract_number', 'title', 'status', 'signed_at', 'start_date', 'end_date', 'notes', 'archived_at'])]
class Contract extends Model implements Searchable
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'signed_at' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function bidSubmission(): BelongsTo
    {
        return $this->belongsTo(BidSubmission::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ContractMilestone::class);
    }

    public function variationOrders(): HasMany
    {
        return $this->hasMany(VariationOrder::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(ContractExtension::class);
    }

    public function liquidatedDamages(): HasMany
    {
        return $this->hasMany(LiquidatedDamage::class);
    }

    public function completionCertificates(): HasMany
    {
        return $this->hasMany(CompletionCertificate::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProcurementActivity::class)->latest();
    }

    public function getTenderAttribute(): ?Tender
    {
        return $this->award?->tender;
    }

    public function searchTitle(): string
    {
        return $this->title;
    }

    public function searchDescription(): ?string
    {
        return $this->notes;
    }

    public function searchKeywords(): array
    {
        return array_values(array_filter([
            $this->contract_number,
            $this->title,
            $this->status,
            $this->project?->name,
            $this->budget?->project?->project_code,
            $this->tender?->title,
            $this->tender?->tender_number,
        ]));
    }

    public function searchRelations(): array
    {
        return [
            'project' => $this->project_id ? [['type' => Project::class, 'id' => $this->project_id, 'title' => $this->project?->name]] : [],
            'budget' => $this->budget_id ? [['type' => Budget::class, 'id' => $this->budget_id, 'title' => $this->budget?->searchTitle()]] : [],
            'tender' => $this->tender ? [['type' => Tender::class, 'id' => $this->tender->id, 'title' => $this->tender->title]] : [],
        ];
    }

    public function searchModule(): string
    {
        return 'procurement';
    }

    public function searchUrl(): string
    {
        return route('admin.procurement.contracts.show', $this, false);
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
            'route_module' => 'contracts',
            'contract_number' => $this->contract_number,
            'project_id' => $this->project_id,
            'budget_id' => $this->budget_id,
            'signed_at' => $this->signed_at?->toDateString(),
        ];
    }
}
