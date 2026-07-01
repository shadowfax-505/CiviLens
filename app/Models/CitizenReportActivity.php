<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['citizen_report_id', 'actor_id', 'event', 'notes', 'old_values', 'new_values', 'ip_address', 'user_agent'])]
class CitizenReportActivity extends Model
{
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(CitizenReport::class, 'citizen_report_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
