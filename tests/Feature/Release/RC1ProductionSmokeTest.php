<?php

use App\Models\Role;
use App\Models\User;
use App\Services\Analytics\ReportBuilder;
use App\Support\Analytics\AnalyticsFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function rc1AdminUser(): User
{
    $role = Role::query()->firstOrCreate(['slug' => config('civiclens.roles.admin')], ['name' => 'Administrator']);
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->sync([$role->id]);

    return $user;
}

/**
 * @return array{response: TestResponse, queries: int, milliseconds: float}
 */
function rc1MeasureRequest(callable $request): array
{
    $queries = 0;
    DB::listen(static function () use (&$queries): void {
        $queries++;
    });

    $startedAt = hrtime(true);
    $response = $request();
    $milliseconds = (hrtime(true) - $startedAt) / 1_000_000;

    return [
        'response' => $response,
        'queries' => $queries,
        'milliseconds' => $milliseconds,
    ];
}

it('keeps public production endpoints within smoke-test query and latency budgets', function (): void {
    foreach (['/healthz', '/version', '/public', '/public/projects', '/public/search?q=road'] as $uri) {
        $result = rc1MeasureRequest(fn (): TestResponse => $this->get($uri));

        $result['response']->assertOk();
        expect($result['queries'])->toBeLessThan(60)
            ->and($result['milliseconds'])->toBeLessThan(1000.0);
    }
});

it('keeps the authenticated executive dashboard within smoke-test query and latency budgets', function (): void {
    $admin = rc1AdminUser();

    $result = rc1MeasureRequest(fn (): TestResponse => $this->actingAs($admin)->get('/dashboard'));

    $result['response']->assertOk();
    expect($result['queries'])->toBeLessThan(160)
        ->and($result['milliseconds'])->toBeLessThan(1500.0);
});

it('generates the largest built-in report formats without excessive memory growth', function (): void {
    $admin = rc1AdminUser();
    $builder = app(ReportBuilder::class);
    $filters = AnalyticsFilters::fromArray([]);
    $startMemory = memory_get_usage(true);

    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $report = $builder->generate('executive', $format, $filters, $admin);
        $content = $builder->content($report);

        expect($content)->toContain('CivicLens');
    }

    $memoryGrowthMb = (memory_get_usage(true) - $startMemory) / 1024 / 1024;

    expect($memoryGrowthMb)->toBeLessThan(16.0);
});
