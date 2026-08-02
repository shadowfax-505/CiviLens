<?php

namespace App\Services\Operations;

use App\Models\CivicIntelligenceRun;
use App\Models\SourceArtifactVersion;
use App\Models\SourceCrawlRun;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function version(): array
    {
        return [
            'app' => config('app.name'),
            'version' => config('app.version'),
            'environment' => config('app.env'),
            'commit' => config('app.commit'),
            'generated_at' => date(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        $checks = [
            'app' => [
                'ok' => true,
                'name' => config('app.name'),
                'version' => config('app.version'),
            ],
            'database' => $this->database(),
            'cache' => $this->cache(),
            'storage' => $this->storage(),
            'queue' => $this->queue(),
            'scheduler' => [
                'ok' => true,
                'configured' => true,
                'integrity_command' => 'civiclens:integrity-run',
            ],
            'integrity' => $this->integrity(),
        ];

        return [
            'status' => collect($checks)->every(fn (array $check): bool => ($check['ok'] ?? false) === true) ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => date(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        return [
            'application' => $this->version(),
            'database' => $this->database() + [
                'migrations_table' => Schema::hasTable('migrations'),
            ],
            'cache' => $this->cache(),
            'queue' => $this->queue() + [
                'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                'pending_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
            ],
            'scheduler' => [
                'configured' => true,
                'integrity_command' => 'civiclens:integrity-run',
                'source_dispatch_command' => 'civiclens:sources-dispatch',
                'frequency' => 'daily',
            ],
            'integrity' => [
                'runs_total' => CivicIntelligenceRun::query()->count(),
                'failed_runs' => CivicIntelligenceRun::query()->where('status', 'failed')->count(),
                'last_run' => $this->latestIntegrityRun(),
            ],
            'ingestion' => $this->ingestion(),
            'generated_at' => date(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function database(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true, 'connection' => config('database.default')];
        } catch (Throwable) {
            return ['ok' => false, 'connection' => config('database.default')];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function cache(): array
    {
        try {
            $key = 'healthz:readiness';
            Cache::put($key, 'ok', 5);

            return ['ok' => Cache::get($key) === 'ok', 'store' => config('cache.default')];
        } catch (Throwable) {
            return ['ok' => false, 'store' => config('cache.default')];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function storage(): array
    {
        $disk = config('filesystems.default', 'local');
        $disk = is_string($disk) ? $disk : 'local';

        try {
            Storage::disk($disk)->exists('.healthz');

            return ['ok' => true, 'disk' => $disk];
        } catch (Throwable) {
            return ['ok' => false, 'disk' => $disk];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        return [
            'ok' => config('queue.default') !== null,
            'connection' => config('queue.default'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integrity(): array
    {
        return [
            'ok' => CivicIntelligenceRun::query()->where('status', 'failed')->where('started_at', '>=', now()->subDay())->doesntExist(),
            'last_run' => $this->latestIntegrityRun(),
        ];
    }

    /** @return array<string, int|string|bool> */
    private function ingestion(): array
    {
        if (! Schema::hasTable('source_endpoints')) {
            return ['enabled' => (bool) config('civiclens.ingestion.enabled'), 'ready' => false];
        }

        return [
            'enabled' => (bool) config('civiclens.ingestion.enabled'),
            'ready' => true,
            'publishers' => SourcePublisher::query()->where('is_active', true)->count(),
            'endpoints' => SourceEndpoint::query()->count(),
            'paused_endpoints' => SourceEndpoint::query()->whereNotNull('paused_at')->count(),
            'failing_endpoints' => SourceEndpoint::query()->where('health_status', 'failing')->count(),
            'runs_last_24_hours' => SourceCrawlRun::query()->where('started_at', '>=', now()->subDay())->count(),
            'failed_runs_last_24_hours' => SourceCrawlRun::query()->whereIn('status', ['failed', 'completed_with_errors'])->where('started_at', '>=', now()->subDay())->count(),
            'quarantined_artifacts' => SourceArtifactVersion::query()->where('is_quarantined', true)->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestIntegrityRun(): ?array
    {
        $run = CivicIntelligenceRun::query()->latest('started_at')->first();

        if (! $run instanceof CivicIntelligenceRun) {
            return null;
        }

        return [
            'uuid' => $run->uuid,
            'status' => $run->status,
            'engine_version' => $run->engine_version,
            'started_at' => $this->dateValue($run->started_at),
            'completed_at' => $this->dateValue($run->completed_at),
            'rules_executed' => $run->rules_executed,
            'indicators_created' => $run->indicators_created,
        ];
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return $value ? (string) $value : null;
    }
}
