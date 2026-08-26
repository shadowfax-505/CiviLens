<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which version of the reader produced a field's score.
 *
 * A score means "these two readings of the same pixels agreed", and what
 * counts as agreement changes when the locator or the scorer changes. When it
 * does, every score taken before is a statement about a reader that no longer
 * exists — and nothing in the schema could say so, so the only way to find out
 * was to sample and notice. 2,611 IMED values sat marked unlocatable by a
 * locator that had since been taught to find them.
 *
 * Null means scored before this column existed, which is the same problem
 * spelled out rather than hidden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_fields', function (Blueprint $table): void {
            $table->string('score_reader_version', 32)->nullable()->after('score_basis');
            $table->index('score_reader_version');
        });
    }

    public function down(): void
    {
        Schema::table('extraction_fields', function (Blueprint $table): void {
            $table->dropIndex(['score_reader_version']);
            $table->dropColumn('score_reader_version');
        });
    }
};
