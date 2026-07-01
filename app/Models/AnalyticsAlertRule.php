<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'category', 'metric_key', 'operator', 'threshold', 'severity', 'message_template', 'is_active', 'sort_order'])]
class AnalyticsAlertRule extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'threshold' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AnalyticsAlert::class);
    }
}
