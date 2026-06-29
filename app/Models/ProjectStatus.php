<?php

namespace App\Models;

use Database\Factories\ProjectStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'sort_order', 'is_terminal'])]
class ProjectStatus extends Model
{
    /** @use HasFactory<ProjectStatusFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_terminal' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
