<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourcePublisher extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'source_class',
        'canonical_url',
        'attribution_name',
        'rights_decision',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(SourceEndpoint::class);
    }
}
