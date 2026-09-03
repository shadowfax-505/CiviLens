<?php

use Illuminate\Support\Facades\Log;
use Monolog\Handler\NullHandler;

it('sends log output nowhere while testing', function (): void {
    // Every other side effect is pointed at a no-op in phpunit.xml —
    // broadcasting, cache, mail, session, and the database in memory. Logging
    // was not, so each run appended to the real application log. It reached
    // 184 MB, 180,024 of its lines written by this suite, and it was among the
    // files that filled the disk and left SQLite unable to write.
    // `discard`, not `null`: env() converts the string "null" to PHP null,
    // which leaves the default channel undefined and makes the first log call
    // throw "Log [] is not defined" instead of discarding anything.
    expect(config('logging.default'))->toBe('discard')
        ->and(config('logging.channels.discard.handler'))->toBe(NullHandler::class);
});

it('leaves the application log file untouched', function (): void {
    // The setting is the mechanism; this is the property. A channel that
    // resolves to a null handler writes no bytes, and asserting the file rather
    // than the config is what would catch a stack channel quietly reinstating a
    // file handler.
    $path = storage_path('logs/laravel.log');
    $before = is_file($path) ? filesize($path) : null;

    Log::info('a line this suite must never persist', ['probe' => true]);

    $after = is_file($path) ? filesize($path) : null;

    expect($after)->toBe($before);
});
