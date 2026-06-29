<?php

namespace App\Models;

use Database\Factories\TenderStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'sort_order', 'is_active'])]
class TenderStatus extends Model
{
    /** @use HasFactory<TenderStatusFactory> */
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
