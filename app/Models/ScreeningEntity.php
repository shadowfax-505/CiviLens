<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningEntity extends Model
{
    protected $fillable = [
        'source_artifact_version_id',
        'dataset',
        'external_id',
        'entity_type',
        'name',
        'normalized_name',
        'aliases',
        'countries',
        'topics',
    ];

    /** @return BelongsTo<SourceArtifactVersion, $this> */
    public function artifactVersion(): BelongsTo
    {
        return $this->belongsTo(SourceArtifactVersion::class, 'source_artifact_version_id');
    }

    protected function casts(): array
    {
        return ['aliases' => 'array'];
    }
}
