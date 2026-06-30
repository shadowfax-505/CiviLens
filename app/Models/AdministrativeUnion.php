<?php

namespace App\Models;

use App\Contracts\Search\Searchable;
use App\Models\Concerns\SearchableGeography;
use Database\Factories\AdministrativeUnionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['upazila_id', 'name', 'type', 'code', 'latitude', 'longitude', 'geojson'])]
class AdministrativeUnion extends Model implements Searchable
{
    /** @use HasFactory<AdministrativeUnionFactory> */
    use HasFactory, SearchableGeography, SoftDeletes;

    protected $table = 'unions';

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geojson' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Upazila, $this>
     */
    public function upazila(): BelongsTo
    {
        return $this->belongsTo(Upazila::class);
    }

    /**
     * @return HasMany<Ward, $this>
     */
    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class, 'union_id');
    }
}
