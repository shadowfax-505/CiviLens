<?php

namespace App\Models;

use Database\Factories\ProcurementMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'is_active'])]
class ProcurementMethod extends Model
{
    /** @use HasFactory<ProcurementMethodFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tenders(): HasMany
    {
        return $this->hasMany(Tender::class);
    }
}
