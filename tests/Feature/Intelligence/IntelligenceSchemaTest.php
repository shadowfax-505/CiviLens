<?php

use App\Models\IntelligenceActivity;
use App\Models\IntelligenceEvidence;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceProcessingJob;
use App\Models\IntelligenceReview;
use App\Models\IntelligenceRule;
use App\Models\IntelligenceRuleType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the normalized intelligence readiness schema', function (): void {
    foreach ([
        'intelligence_rule_types',
        'intelligence_rules',
        'intelligence_indicators',
        'intelligence_evidence',
        'intelligence_reviews',
        'intelligence_processing_jobs',
        'intelligence_activities',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('links indicators to rules evidence reviews jobs and activities', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id, 'updated_by' => $user->id]);
    $type = IntelligenceRuleType::factory()->create(['slug' => 'project-risk']);
    $rule = IntelligenceRule::factory()->create([
        'intelligence_rule_type_id' => $type->id,
        'module' => 'projects',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    $indicator = IntelligenceIndicator::factory()->create([
        'intelligence_rule_id' => $rule->id,
        'source_type' => Project::class,
        'source_id' => $project->id,
        'module' => 'projects',
    ]);
    $evidence = IntelligenceEvidence::factory()->create([
        'intelligence_indicator_id' => $indicator->id,
        'evidenceable_type' => Project::class,
        'evidenceable_id' => $project->id,
    ]);
    $review = IntelligenceReview::factory()->create([
        'intelligence_indicator_id' => $indicator->id,
        'reviewed_by' => $user->id,
    ]);
    $job = IntelligenceProcessingJob::factory()->create([
        'target_type' => Project::class,
        'target_id' => $project->id,
    ]);
    $activity = IntelligenceActivity::factory()->create([
        'intelligence_indicator_id' => $indicator->id,
        'actor_id' => $user->id,
    ]);

    expect($indicator->rule->is($rule))->toBeTrue()
        ->and($indicator->source->is($project))->toBeTrue()
        ->and($indicator->evidence->first()->is($evidence))->toBeTrue()
        ->and($indicator->reviews->first()->is($review))->toBeTrue()
        ->and($job->target->is($project))->toBeTrue()
        ->and($indicator->activities->first()->is($activity))->toBeTrue();
});

it('blocks deletion for intelligence history records', function (): void {
    $indicator = IntelligenceIndicator::factory()->create();
    $evidence = IntelligenceEvidence::factory()->create(['intelligence_indicator_id' => $indicator->id]);
    $activity = IntelligenceActivity::factory()->create(['intelligence_indicator_id' => $indicator->id]);

    expect($indicator->delete())->toBeFalse()
        ->and($evidence->delete())->toBeFalse()
        ->and($activity->delete())->toBeFalse();
});
