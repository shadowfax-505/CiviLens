<?php

namespace App\Services\Intelligence;

use App\Models\CivicIntelligenceRun;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Throwable;

class CivicIntegrityEngineService
{
    private const ENGINE_VERSION = '13.1.0';

    public function __construct(private readonly RuleExecutionService $rules) {}

    public function run(?User $actor = null): CivicIntelligenceRun
    {
        $rules = IntelligenceRule::query()
            ->where('is_active', true)
            ->orderBy('module')
            ->orderBy('slug')
            ->get();

        $run = CivicIntelligenceRun::query()->create([
            'engine_version' => self::ENGINE_VERSION,
            'status' => 'running',
            'triggered_by' => $actor?->id,
            'started_at' => now(),
            'threshold_snapshot' => $this->thresholdSnapshot($rules),
        ]);

        try {
            $created = 0;

            foreach ($rules as $rule) {
                if (! $rule instanceof IntelligenceRule) {
                    continue;
                }

                $indicators = $this->rules->run($rule, $actor);
                $created += $indicators->count();

                $indicators->each(function (IntelligenceIndicator $indicator) use ($run, $rule): void {
                    $metadata = is_array($indicator->metadata) ? $indicator->metadata : [];

                    $indicator->forceFill([
                        'metadata' => array_merge($metadata, [
                            'engine' => 'civic_integrity',
                            'engine_run_id' => $run->id,
                            'engine_version' => self::ENGINE_VERSION,
                            'rule_slug' => $rule->slug,
                        ]),
                    ])->save();
                });
            }

            $run->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'rules_executed' => $rules->count(),
                'indicators_created' => $created,
                'summary_payload' => [
                    'modules' => $rules->pluck('module')->unique()->values()->all(),
                    'critical' => IntelligenceIndicator::query()->where('metadata->engine_run_id', $run->id)->where('severity', 'critical')->count(),
                    'warning' => IntelligenceIndicator::query()->where('metadata->engine_run_id', $run->id)->where('severity', 'warning')->count(),
                    'review_status' => 'human_review_required',
                ],
            ])->save();

            return $run->refresh();
        } catch (Throwable $throwable) {
            $run->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
                'notes' => $throwable->getMessage(),
            ])->save();

            throw $throwable;
        }
    }

    /**
     * @param  Collection<int, IntelligenceRule>  $rules
     * @return array<string, array<string, mixed>>
     */
    private function thresholdSnapshot(Collection $rules): array
    {
        return $rules
            ->filter(fn (IntelligenceRule $rule): bool => $rule->slug !== '')
            ->mapWithKeys(fn (IntelligenceRule $rule): array => [
                $rule->slug => [
                    'module' => $rule->module,
                    'version' => $rule->version,
                    'thresholds' => $rule->thresholds ?? [],
                    'configuration' => $rule->configuration ?? [],
                ],
            ])
            ->all();
    }
}
