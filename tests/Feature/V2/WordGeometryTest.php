<?php

use App\Models\ExtractionPage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores words as a structure rather than a blob of text', function (): void {
    // A cast, so callers get an array and a later reader is not left parsing
    // JSON by hand at the point it matters.
    $page = ExtractionPage::factory()->create([
        'recognized_words' => [['t' => '৫৪৭২৫৩', 'c' => 91.2, 'l' => 154, 'y' => 68, 'w' => 46, 'h' => 10]],
    ]);

    expect($page->fresh()->recognized_words)->toBeArray()
        ->and($page->fresh()->recognized_words[0]['t'])->toBe('৫৪৭২৫৩')
        ->and($page->fresh()->recognized_words[0]['l'])->toBe(154);
});
