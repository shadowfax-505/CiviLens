<?php

namespace App\Services\Extraction;

use App\Data\Extraction\NativeExtractionResult;
use App\Exceptions\Extraction\ExtractionFailed;
use App\Models\ExtractionRun;
use App\Models\SourceArtifactVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class NativeExtractionService
{
    public function __construct(
        private readonly NativeExtractorRegistry $registry,
        private readonly PageRoutingPolicy $routing,
        private readonly ScriptClassifier $scripts,
        private readonly ArtifactWorkspace $workspace,
    ) {}

    public function extract(SourceArtifactVersion $artifact, ?User $actor = null): ExtractionRun
    {
        if ($artifact->is_quarantined) {
            throw new ExtractionFailed('Quarantined artifacts are not eligible for extraction.');
        }

        $run = ExtractionRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'source_artifact_version_id' => $artifact->getKey(),
            'triggered_by' => $actor?->getKey(),
            'status' => 'running',
            'routing_decision' => 'pending',
            'engine' => 'pending',
            'engine_version' => 'pending',
            'config_hash' => $this->configHash(),
            'started_at' => now(),
        ]);

        $temporaryPath = null;

        try {
            $temporaryPath = $this->workspace->materialize($artifact);
            $result = $this->registry->for($artifact->media_type)->extract($temporaryPath);

            return $this->record($run, $result);
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'failure_reason' => str($exception instanceof ExtractionFailed ? $exception->getMessage() : 'Native extraction failed.')->limit(500)->toString(),
                'completed_at' => now(),
            ])->save();

            throw $exception;
        } finally {
            $this->workspace->discard($temporaryPath);
        }
    }

    private function record(ExtractionRun $run, NativeExtractionResult $result): ExtractionRun
    {
        $paths = [];

        DB::transaction(function () use ($run, $result, &$paths): void {
            foreach ($result->pages as $page) {
                $path = $this->routing->decide($page);
                $paths[] = $path;
                $isNative = $path === PageRoutingPolicy::NATIVE;

                $run->pages()->create([
                    'page_number' => $page->pageNumber,
                    'script_class' => $this->scripts->classify($page->text),
                    'text_layer_density' => $page->density(),
                    'extraction_path' => $path,
                    'confidence' => $isNative ? 1.0 : null,
                    'extracted_text' => $isNative ? $page->text : null,
                    'content_hash' => $isNative ? hash('sha256', $page->text) : null,
                    'character_count' => $page->characterCount(),
                    'word_count' => $page->wordCount(),
                ]);
            }

            $native = count(array_filter($paths, fn (string $path): bool => $path === PageRoutingPolicy::NATIVE));

            $run->forceFill([
                'status' => 'completed',
                'routing_decision' => $this->routing->summarize($paths),
                'engine' => $result->engine,
                'engine_version' => $result->engineVersion,
                'page_count' => $result->pageCount(),
                'pages_native' => $native,
                'duration_ms' => $result->durationMs,
                'peak_memory_bytes' => memory_get_peak_usage(true),
                'completed_at' => now(),
            ])->save();
        });

        return $run->refresh();
    }

    /**
     * Pins the settings that change extraction output so a run can be reproduced
     * or excluded from a calibration set when the configuration moves.
     */
    private function configHash(): string
    {
        return hash('sha256', json_encode([
            'threshold' => config('civiclens.extraction.native_density_threshold'),
            'max_pages' => config('civiclens.extraction.max_pages'),
            'page_width' => config('civiclens.extraction.default_page_width_points'),
            'page_height' => config('civiclens.extraction.default_page_height_points'),
        ], JSON_THROW_ON_ERROR));
    }
}
