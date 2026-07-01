<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'user_id', 'name', 'dashboard', 'format', 'status', 'filters', 'payload', 'storage_disk', 'storage_path', 'generated_at', 'expires_at'])]
class AnalyticsReport extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'payload' => 'array',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
