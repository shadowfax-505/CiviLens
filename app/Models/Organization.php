<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'established_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function companyType(): BelongsTo
    {
        return $this->belongsTo(OrganizationCompanyType::class, 'organization_company_type_id');
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(OrganizationIndustry::class, 'organization_industry_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(ContractorProfile::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(BranchOffice::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ContractorActivity::class);
    }
}
