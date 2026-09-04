<?php

use Symfony\Component\Process\Process;

/** The detector's own interpreter, or null when this machine has no table toolchain. */
function tableDetectorPython(): ?string
{
    $python = (string) config('civiclens.extraction.tables.python', '');

    return $python !== '' && is_executable($python) ? $python : null;
}

it('stops itself at its budget, whatever the caller can do about it', function (): void {
    // The caller's timeout is a check made while PHP waits on this process's
    // output, and a worker blocked in that read on a machine that is paging
    // never gets to make it. One page ran 3h 14m against an 840-second budget,
    // past the job's 900 and the worker's 1800, and the worker did not recycle
    // at its own --max-time. SIGALRM is delivered by the kernel, so the ceiling
    // holds from the inside.
    //
    // Three seconds is below the time PaddlePaddle needs merely to import, so
    // this deliberately kills it inside native code that would not unwind —
    // which is why the handler exits hard rather than raising.
    $python = tableDetectorPython();

    $script = base_path('tools/table-structure/detect_tables.py');
    $image = base_path('tests/Fixtures/Extraction/scanned-text.pdf');

    $process = new Process(
        [(string) $python, $script, $image],
        null,
        ['CIVICLENS_TABLE_BUDGET_SECONDS' => '3'],
    );

    $process->setTimeout(60.0);

    $started = microtime(true);
    $process->run();
    $elapsed = microtime(true) - $started;

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('TimeBudgetExceeded')
        // Comfortably inside the caller's own 60s ceiling, so the failure is
        // the script's and not Symfony's.
        ->and($elapsed)->toBeLessThan(30.0);
})->skip(fn (): bool => tableDetectorPython() === null, 'the table detection toolchain is not installed');

it('passes the configured budget down to the script', function (): void {
    // Both ends are kept: the caller reports a timeout properly when it can,
    // and the script is the floor under it when it cannot.
    $source = (string) file_get_contents(base_path('app/Services/Extraction/TableStructureDetector.php'));

    expect($source)->toContain('CIVICLENS_TABLE_BUDGET_SECONDS')
        ->and($source)->toContain("config('civiclens.extraction.tables.timeout_seconds'");

    $script = (string) file_get_contents(base_path('tools/table-structure/detect_tables.py'));

    expect($script)->toContain('signal.alarm')
        ->and($script)->toContain('CIVICLENS_TABLE_BUDGET_SECONDS')
        // Read before the model is imported, or the budget cannot cover the
        // import — which is where the three-hour page was stuck.
        ->and(strpos($script, 'enforce_own_deadline()'))
        ->toBeLessThan(strpos($script, 'from paddleocr import'));
});
