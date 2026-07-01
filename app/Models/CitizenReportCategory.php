<?php

namespace App\Models;

use Database\Factories\CitizenReportCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'is_active', 'sort_order'])]
class CitizenReportCategory extends Model
{
    /** @use HasFactory<CitizenReportCategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CitizenReport::class);
    }
}
