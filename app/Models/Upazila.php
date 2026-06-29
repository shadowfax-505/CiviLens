<?php

namespace App\Models;

use Database\Factories\UpazilaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['district_id', 'name', 'code', 'latitude', 'longitude', 'geojson'])]
class Upazila extends Model
{
    /** @use HasFactory<UpazilaFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geojson' => 'array',
        ];
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return HasMany<AdministrativeUnion, $this>
     */
    public function unions(): HasMany
    {
        return $this->hasMany(AdministrativeUnion::class, 'upazila_id');
    }
}
