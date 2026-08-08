<?php

namespace App\Services\Extraction;

use App\Models\ExtractionExperiment;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * A bounded, auditable improvement loop over extraction configurations.
 *
 * The loop may only measure and record. It cannot promote a configuration,
 * change what production runs, or alter a finished result -- promotion is a
 * separate human act, and the ledger refuses edits to completed runs. A loop
 * that could adopt its own winner would be optimising against a record it
 * controls.
 *
 * Every run is bounded three ways: a maximum iteration count, a wall-clock
 * budget, and a kill switch checked before each iteration. Without all three an
 * "improvement loop" is just an unbounded process with a nice name.
 */
class ExtractionImprovementLoop
{
    public const KILL_SWITCH = 'civiclens.extraction.loop.stop';

    /**
     * @param  list<array<string, mixed>>  $candidates  configurations to measure, in order
     * @param  Closure(array<string, mixed>): array{fields: int, correct: int, metrics?: array<string, mixed>}  $measure
     * @return array<string, mixed>
     */
    public function run(
        string $loop,
        string $corpus,
        array $candidates,
        Closure $measure,
        int $maxIterations = 5,
        int $budgetSeconds = 600,
    ): array {
        $startedAt = microtime(true);
        $planned = min(count($candidates), max(1, $maxIterations));
        $completed = [];
        $stoppedBecause = null;

        foreach (array_slice($candidates, 0, $planned) as $iteration => $config) {
            if ($this->stopRequested()) {
                $stoppedBecause = 'kill switch';
                break;
            }

            if ((microtime(true) - $startedAt) >= $budgetSeconds) {
                $stoppedBecause = 'time budget';
                break;
            }

            $completed[] = $this->iterate($loop, $corpus, $iteration + 1, $config, $measure);
        }

        if ($stoppedBecause === null && count($completed) < count($candidates)) {
            $stoppedBecause = 'iteration limit';
        }

        return [
            'loop' => $loop,
            'corpus' => $corpus,
            'candidates_supplied' => count($candidates),
            'iterations_run' => count($completed),
            'stopped_because' => $stoppedBecause ?? 'candidates exhausted',
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'best' => $this->best($completed),
            'promoted' => null,
            'promotion_note' => 'Promotion is a human decision. This loop records measurements and never changes what production runs.',
            'experiments' => array_map(fn (ExtractionExperiment $e): array => [
                'uuid' => $e->uuid,
                'iteration' => $e->iteration,
                'status' => $e->status,
                'field_accuracy' => $e->field_accuracy,
                'config' => $e->config,
            ], $completed),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  Closure(array<string, mixed>): array{fields: int, correct: int, metrics?: array<string, mixed>}  $measure
     */
    private function iterate(string $loop, string $corpus, int $iteration, array $config, Closure $measure): ExtractionExperiment
    {
        $startedAt = microtime(true);

        $experiment = ExtractionExperiment::query()->create([
            'uuid' => (string) Str::uuid(),
            'loop' => $loop,
            'corpus' => $corpus,
            'iteration' => $iteration,
            'config_hash' => hash('sha256', json_encode($config, JSON_THROW_ON_ERROR)),
            'config' => $config,
            'status' => 'running',
        ]);

        try {
            $result = $measure($config);
            $fields = max(0, $result['fields']);
            $correct = max(0, $result['correct']);

            $experiment->forceFill([
                'status' => 'completed',
                'fields' => $fields,
                'correct' => $correct,
                'field_accuracy' => $fields > 0 ? round($correct / $fields, 6) : null,
                'metrics' => $result['metrics'] ?? null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ])->save();
        } catch (Throwable $exception) {
            // A failed candidate is retained, not discarded. Losing failures
            // makes a loop look better than it was.
            $experiment->forceFill([
                'status' => 'failed',
                'failure_reason' => str($exception->getMessage())->limit(500)->toString(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ])->save();
        }

        return $experiment->refresh();
    }

    /**
     * @param  list<ExtractionExperiment>  $completed
     * @return array{uuid: string, iteration: int, field_accuracy: float, config: array<string, mixed>}|null
     */
    private function best(array $completed): ?array
    {
        $scored = array_filter($completed, fn (ExtractionExperiment $e): bool => $e->field_accuracy !== null);

        if ($scored === []) {
            return null;
        }

        $scored = array_values($scored);
        usort($scored, fn (ExtractionExperiment $a, ExtractionExperiment $b): int => $b->field_accuracy <=> $a->field_accuracy);
        $winner = $scored[0];

        return [
            'uuid' => (string) $winner->uuid,
            'iteration' => (int) $winner->iteration,
            'field_accuracy' => (float) $winner->field_accuracy,
            'config' => is_array($winner->config) ? $winner->config : [],
        ];
    }

    public function stopRequested(): bool
    {
        return (bool) Cache::get(self::KILL_SWITCH, false);
    }

    public function requestStop(): void
    {
        Cache::put(self::KILL_SWITCH, true, now()->addDay());
    }

    public function clearStop(): void
    {
        Cache::forget(self::KILL_SWITCH);
    }
}
