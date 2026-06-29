<?php

namespace App\Models;

use Database\Factories\BudgetStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'sort_order', 'is_active'])]
class BudgetStatus extends Model
{
    /** @use HasFactory<BudgetStatusFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }
}
