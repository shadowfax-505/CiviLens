<?php

namespace App\Models;

use Database\Factories\TenderCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'is_active'])]
class TenderCategory extends Model
{
    /** @use HasFactory<TenderCategoryFactory> */
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
