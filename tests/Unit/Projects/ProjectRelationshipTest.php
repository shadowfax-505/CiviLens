<?php

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('connects projects to lookup data geography users hierarchy and activity', function (): void {
    $creator = User::factory()->create();
    $parent = Project::factory()->create(['created_by' => $creator->id]);
    $child = Project::factory()->for($parent, 'parent')->create(['created_by' => $creator->id]);
    ProjectActivity::factory()->for($child)->for($creator, 'actor')->create(['event' => 'created']);

    expect($child->parent->is($parent))->toBeTrue()
        ->and($parent->children()->whereKey($child)->exists())->toBeTrue()
        ->and($child->agency)->not->toBeNull()
        ->and($child->category)->not->toBeNull()
        ->and($child->status)->not->toBeNull()
        ->and($child->priority)->not->toBeNull()
        ->and($child->fundingSource)->not->toBeNull()
        ->and($child->fiscalYear)->not->toBeNull()
        ->and($child->creator->is($creator))->toBeTrue()
        ->and($child->activities()->where('event', 'created')->exists())->toBeTrue();
});
