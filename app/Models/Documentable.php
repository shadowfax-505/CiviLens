<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Documentable extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): ?Model
    {
        $class = $this->documentable_type;

        if (! is_a($class, Model::class, true)) {
            return null;
        }

        return $class::query()->find($this->documentable_id);
    }
}
