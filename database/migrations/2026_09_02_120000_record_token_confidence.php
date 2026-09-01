<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How well the recognizer read this particular value.
 *
 * Distinct from the page's mean, which ADR-023 records as the wrong unit: over
 * 719 labelled values, tokens read at 80 or above were wrong 3.6% of the time
 * and tokens below 80 were wrong 28%. The quantity was being recomputed from
 * `recognized_words` every time anyone asked, which makes it unqueryable — a
 * stratum you cannot select is a stratum you cannot audit.
 *
 * Null where the value cannot be found among the page's words, which is the
 * same condition that makes it unlocatable and defer by rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_fields', function (Blueprint $table): void {
            $table->decimal('token_confidence', 7, 4)->nullable()->after('confidence');
            $table->index(['decision', 'token_confidence']);
        });
    }

    public function down(): void
    {
        Schema::table('extraction_fields', function (Blueprint $table): void {
            $table->dropIndex(['decision', 'token_confidence']);
            $table->dropColumn('token_confidence');
        });
    }
};
