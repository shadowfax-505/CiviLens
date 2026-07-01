<?php

namespace App\Models;

use Database\Factories\CitizenReportStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'is_default', 'is_terminal', 'is_active', 'sort_order'])]
class CitizenReportStatus extends Model
{
    /** @use HasFactory<CitizenReportStatusFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_terminal' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CitizenReport::class);
    }
}
