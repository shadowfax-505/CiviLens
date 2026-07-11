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

        $created = 0;
        $executed = 0;
        $currentRule = null;

        try {

            foreach ($rules as $rule) {
                if (! $rule instanceof IntelligenceRule) {
                    continue;
                }

                $currentRule = $rule;
                $indicators = $this->rules->run($rule, $actor);
                $created += $indicators->count();
                $executed++;

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
            $failedRule = $currentRule instanceof IntelligenceRule ? $currentRule->slug : null;

            $run->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
                'rules_executed' => $executed,
                'indicators_created' => $created,
                'notes' => $failedRule === null
                    ? 'Civic Integrity Engine failed before rule execution.'
                    : 'Civic Integrity Engine failed while executing rule '.$failedRule.'.',
                'summary_payload' => [
                    'failed_rule' => $failedRule,
                    'error_class' => class_basename($throwable),
                    'review_status' => 'technical_review_required',
                ],
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
