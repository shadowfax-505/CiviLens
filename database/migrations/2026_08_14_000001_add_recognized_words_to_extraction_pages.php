<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keep the word boxes the recognizer already produces.
 *
 * Tesseract reports a position for every word and the pipeline threw them away,
 * storing only the flattened text. Without them a page is a wall of words with
 * no geometry, so a figure lifted from a table cannot be traced back to the row
 * or column it sat in — which is what a reviewer needs and what any later
 * analysis depends on.
 *
 * A column rather than a table: about 245 words per page across 1,203 pages
 * would be roughly 294,000 rows that are only ever read alongside their own
 * page, and never queried across pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_pages', function (Blueprint $table): void {
            $table->json('recognized_words')->nullable()->after('extracted_text');
        });
    }

    public function down(): void
    {
        Schema::table('extraction_pages', function (Blueprint $table): void {
            $table->dropColumn('recognized_words');
        });
    }
};
