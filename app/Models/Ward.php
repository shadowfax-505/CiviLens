<?php

namespace App\Models;

use Database\Factories\WardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['union_id', 'name', 'code', 'latitude', 'longitude', 'geojson'])]
class Ward extends Model
{
    /** @use HasFactory<WardFactory> */
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
     * @return BelongsTo<AdministrativeUnion, $this>
     */
    public function union(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnion::class, 'union_id');
    }
}
