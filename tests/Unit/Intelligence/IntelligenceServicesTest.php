<?php

use App\Events\IntelligenceIndicatorDetected;
use App\Events\IntelligenceIndicatorReviewed;
use App\Events\IntelligenceProcessingJobQueued;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceProcessingJob;
use App\Models\IntelligenceRule;
use App\Models\Project;
use App\Models\SearchIndex;
use App\Models\User;
use App\Services\Intelligence\EvidenceBuilder;
use App\Services\Intelligence\IndicatorScoringService;
use App\Services\Intelligence\IntelligenceDashboardService;
use App\Services\Intelligence\IntelligenceManager;
use App\Services\Intelligence\ProcessingJobService;
use App\Services\Intelligence\ReviewWorkflowService;
use App\Services\Search\SearchIndexingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('detects a rule based indicator with explainable evidence', function (): void {
    Event::fake();
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'progress_percentage' => 25,
        'planned_end_date' => now()->subDays(10),
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    $rule = IntelligenceRule::factory()->create([
        'slug' => 'project-delay-risk',
        'module' => 'projects',
        'thresholds' => ['days_overdue' => 1, 'max_progress' => 90],
    ]);

    $indicator = app(IntelligenceManager::class)->runRule($rule, $user)->first();

    expect($indicator)->toBeInstanceOf(IntelligenceIndicator::class)
        ->and($indicator->source->is($project))->toBeTrue()
        ->and($indicator->evidence)->toHaveCount(1)
        ->and($indicator->status)->toBe('pending');

    Event::assertDispatched(IntelligenceIndicatorDetected::class);
});

it('normalizes severity and confidence scores', function (): void {
    $score = app(IndicatorScoringService::class)->score(135, ['warning' => 40, 'critical' => 100]);

    expect($score['severity'])->toBe('critical')
        ->and($score['confidence'])->toBe(100);
});

it('records review transitions and queues processing preparation', function (): void {
    Event::fake();
    Queue::fake();
    $user = User::factory()->create();
    $indicator = IntelligenceIndicator::factory()->create();

    $review = app(ReviewWorkflowService::class)->review($indicator, $user, [
        'status' => 'accepted',
        'notes' => 'Evidence is source-backed.',
    ]);
    $job = app(ProcessingJobService::class)->queue(Project::factory()->create(), 'ocr_preparation', $user);

    expect($review->status)->toBe('accepted')
        ->and($indicator->refresh()->status)->toBe('accepted')
        ->and($job)->toBeInstanceOf(IntelligenceProcessingJob::class)
        ->and($job->status)->toBe('queued');

    Event::assertDispatched(IntelligenceIndicatorReviewed::class);
    Event::assertDispatched(IntelligenceProcessingJobQueued::class);
});

it('indexes intelligence indicators through universal search', function (): void {
    $indicator = IntelligenceIndicator::factory()->create([
        'title' => 'Delayed bridge project',
        'description' => 'Planned completion date passed with low progress.',
    ]);

    app(SearchIndexingService::class)->index($indicator);

    expect(SearchIndex::query()
        ->where('searchable_type', IntelligenceIndicator::class)
        ->where('searchable_id', $indicator->id)
        ->where('module', 'intelligence')
        ->exists())->toBeTrue();
});

it('builds dashboard summaries from source intelligence records', function (): void {
    IntelligenceIndicator::factory()->create(['severity' => 'critical', 'status' => 'pending']);
    IntelligenceIndicator::factory()->create(['severity' => 'warning', 'status' => 'accepted']);

    $summary = app(IntelligenceDashboardService::class)->summary();

    expect($summary['cards']['pending_reviews'])->toBe(1)
        ->and($summary['cards']['high_risk_signals'])->toBe(1)
        ->and($summary['charts']['status_distribution'])->toHaveKey('pending');
});

it('creates evidence records for polymorphic source records', function (): void {
    $project = Project::factory()->create();
    $indicator = IntelligenceIndicator::factory()->create([
        'source_type' => Project::class,
        'source_id' => $project->id,
    ]);

    $evidence = app(EvidenceBuilder::class)->link($indicator, $project, [
        'label' => 'Project schedule',
        'summary' => 'The planned end date is in the past.',
        'weight' => 80,
    ]);

    expect($evidence->evidenceable->is($project))->toBeTrue()
        ->and($evidence->label)->toBe('Project schedule');
});
