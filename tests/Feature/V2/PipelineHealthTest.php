<?php

use App\Models\SourceEndpoint;
use App\Services\Extraction\PipelineHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports the table sidecar as failing when its interpreter is gone', function (): void {
    // This is the failure that motivated the check. The environment was purged
    // from /tmp, every table job failed with "No module named 'paddleocr'", and
    // the only visible symptom was pages that stopped gaining tables — which
    // looks exactly like a corpus that has none.
    config(['civiclens.extraction.tables.python' => '/tmp/gone/bin/python']);

    $report = app(PipelineHealthReport::class)->build();

    expect($report['healthy'])->toBeFalse()
        ->and($report['failing'])->toContain('table_sidecar')
        ->and($report['checks']['table_sidecar']['detail'])->toContain('Interpreter missing');
});

it('warns when the sidecar is installed somewhere the system will delete it', function (): void {
    // Present today and gone next week is worse than absent: it works long
    // enough to be trusted.
    config(['civiclens.extraction.tables.python' => '/tmp/civiclens-pp/bin/python3']);

    $report = app(PipelineHealthReport::class)->build();

    expect($report['checks']['table_sidecar']['ok'])->toBeFalse()
        ->and($report['checks']['table_sidecar']['detail'])->toContain('purges');
});

it('does not call an unconfigured browser a fault', function (): void {
    // A machine that never opted in is not broken; publishers needing a browser
    // are refused rather than misread.
    config(['civiclens.ingestion.browser.node' => '']);

    $report = app(PipelineHealthReport::class)->build();

    expect($report['checks']['browser_renderer']['ok'])->toBeTrue()
        ->and($report['failing'])->not->toContain('browser_renderer');
});

it('fails a browser that is configured but not executable', function (): void {
    // Configured and broken is a fault, because something was expected to work.
    config(['civiclens.ingestion.browser.node' => '/no/such/node']);

    expect(app(PipelineHealthReport::class)->build()['failing'])->toContain('browser_renderer');
});

it('fails a CA bundle that is configured but unreadable', function (): void {
    // Silently falling back to the system roots would make three publishers
    // unverifiable again, with nothing saying why.
    config(['civiclens.ingestion.ca_bundle' => '/no/such/bundle.pem']);

    expect(app(PipelineHealthReport::class)->build()['failing'])->toContain('ca_bundle');
});

it('counts endpoints that are failing while still active', function (): void {
    SourceEndpoint::factory()->create(['health_status' => 'failing', 'paused_at' => null]);
    SourceEndpoint::factory()->create(['health_status' => 'healthy', 'paused_at' => null]);

    $endpoints = app(PipelineHealthReport::class)->build()['checks']['endpoints'];

    expect($endpoints['failing'])->toBe(1)
        ->and($endpoints['active'])->toBe(2);
});
