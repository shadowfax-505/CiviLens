<?php

use App\Jobs\ExtractAcquiredArtifact;
use App\Models\ExtractionRun;
use App\Models\SourceArtifactVersion;
use App\Services\Extraction\NativeExtractionService;
use App\Services\Extraction\SelectiveOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('reads an artifact rather than leaving it stored and unread', function (): void {
    // Acquisition stored artifacts and nothing read them: after two publishers
    // and ten fetched audit reports there were zero extraction runs, because
    // the extraction spine was never reachable from the pipeline.
    $artifact = SourceArtifactVersion::factory()->create();
    $run = ExtractionRun::factory()->create();

    $this->mock(NativeExtractionService::class, function (MockInterface $mock) use ($artifact, $run): void {
        $mock->shouldReceive('extract')
            ->once()
            ->withArgs(fn (SourceArtifactVersion $given): bool => $given->is($artifact))
            ->andReturn($run);
    });

    // Native extraction first, because a text layer is exact. OCR then handles
    // only the pages native extraction could not read.
    $this->mock(SelectiveOcrService::class, function (MockInterface $mock) use ($run): void {
        $mock->shouldReceive('process')->once()->andReturn($run);
    });

    (new ExtractAcquiredArtifact($artifact->id))->handle(
        app(NativeExtractionService::class),
        app(SelectiveOcrService::class),
    );
});

it('does nothing when the artifact has been removed', function (): void {
    $this->mock(NativeExtractionService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('extract');
    });

    (new ExtractAcquiredArtifact(999999))->handle(
        app(NativeExtractionService::class),
        app(SelectiveOcrService::class),
    );

    expect(true)->toBeTrue();
});
