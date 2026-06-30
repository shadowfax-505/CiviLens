<?php

namespace App\Models;

use App\Contracts\Search\Searchable;
use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'parent_id',
    'agency_type_id',
    'country_id',
    'division_id',
    'district_id',
    'upazila_id',
    'union_id',
    'ward_id',
    'name',
    'short_name',
    'slug',
    'description',
    'contact_person',
    'website',
    'email',
    'phone',
    'address',
    'status',
])]
class Agency extends Model implements Searchable
{
    /** @use HasFactory<AgencyFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'inactive', 'archived'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Agency, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<AgencyType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AgencyType::class, 'agency_type_id');
    }

    /**
     * @return BelongsTo<AgencyType, $this>
     */
    public function agencyType(): BelongsTo
    {
        return $this->type();
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return BelongsTo<Upazila, $this>
     */
    public function upazila(): BelongsTo
    {
        return $this->belongsTo(Upazila::class);
    }

    /**
     * @return BelongsTo<AdministrativeUnion, $this>
     */
    public function union(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnion::class, 'union_id');
    }

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('relationship')->withTimestamps();
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tenders(): HasMany
    {
        return $this->hasMany(Tender::class);
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
            $this->name,
            $this->short_name,
            $this->slug,
            $this->type?->name,
            $this->status,
            $this->email,
            $this->phone,
            $this->country?->name,
            $this->division?->name,
            $this->district?->name,
        ]));
    }

    public function searchRelations(): array
    {
        return [
            'parent' => $this->parent_id ? [['type' => Agency::class, 'id' => $this->parent_id, 'title' => $this->parent?->name]] : [],
            'projects' => $this->projects()->limit(10)->get(['id', 'name'])->map(fn (Project $project): array => ['type' => Project::class, 'id' => $project->id, 'title' => $project->name])->all(),
            'procurement' => $this->tenders()->limit(10)->get(['id', 'title'])->map(fn (Tender $tender): array => ['type' => Tender::class, 'id' => $tender->id, 'title' => $tender->title])->all(),
        ];
    }

    public function searchModule(): string
    {
        return 'agencies';
    }

    public function searchUrl(): string
    {
        return route('admin.agencies.index', ['search' => $this->name], false);
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
            'route_module' => 'agencies',
            'agency_type_id' => $this->agency_type_id,
            'country_id' => $this->country_id,
            'division_id' => $this->division_id,
            'district_id' => $this->district_id,
        ];
    }
}
