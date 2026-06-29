<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractorProfile extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_suspended' => 'boolean',
            'is_blacklisted' => 'boolean',
            'is_public' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContractorCategory::class, 'contractor_category_id');
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(ContractorClassification::class, 'contractor_classification_id');
    }

    public function registrationStatus(): BelongsTo
    {
        return $this->belongsTo(ContractorRegistrationStatus::class, 'contractor_registration_status_id');
    }

    public function riskLevel(): BelongsTo
    {
        return $this->belongsTo(ContractorRiskLevel::class, 'contractor_risk_level_id');
    }

    public function complianceRecords(): HasMany
    {
        return $this->hasMany(ComplianceRecord::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(ContractorLicense::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(ContractorCertification::class);
    }

    public function performanceSnapshots(): HasMany
    {
        return $this->hasMany(ContractorPerformanceSnapshot::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ContractorActivity::class);
    }
}
