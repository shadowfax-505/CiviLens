<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => [
                'ok' => true,
                'name' => config('app.name'),
            ],
            'database' => $this->database(),
            'cache' => $this->cache(),
            'storage' => $this->storage(),
            'queue' => [
                'ok' => config('queue.default') !== null,
                'connection' => config('queue.default'),
            ],
        ];

        $ok = collect($checks)->every(fn (array $check): bool => ($check['ok'] ?? false) === true);

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
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
}
