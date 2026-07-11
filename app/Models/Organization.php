<?php

namespace App\Models;

use App\Contracts\Search\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model implements Searchable
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'established_date' => 'date',
            'archived_at' => 'datetime',
            'headquarters_latitude' => 'decimal:7',
            'headquarters_longitude' => 'decimal:7',
            'headquarters_geojson' => 'array',
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

    public function searchTitle(): string
    {
        return $this->legal_name;
    }

    public function searchDescription(): ?string
    {
        return $this->headquarters_address;
    }

    public function searchKeywords(): array
    {
        return array_values(array_filter([
            $this->legal_name,
            $this->trade_name,
            $this->registration_number,
            $this->tax_identification_number,
            $this->companyType?->name,
            $this->industry?->name,
            $this->email,
            $this->phone,
            $this->country?->name,
            $this->status,
        ]));
    }

    public function searchRelations(): array
    {
        return [
            'contractor_profile' => $this->profile ? [['type' => ContractorProfile::class, 'id' => $this->profile->id, 'title' => $this->legal_name]] : [],
        ];
    }

    public function searchModule(): string
    {
        return 'contractors';
    }

    public function searchUrl(): string
    {
        return route('admin.contractors.organizations.show', $this, false);
    }

    public function publicSearchUrl(): string
    {
        return route('public.contractors.show', $this, false);
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
            'route_module' => 'organizations',
            'registration_number' => $this->registration_number,
            'organization_company_type_id' => $this->organization_company_type_id,
            'organization_industry_id' => $this->organization_industry_id,
            'country_id' => $this->country_id,
            'public_url' => $this->publicSearchUrl(),
        ];
    }
}
