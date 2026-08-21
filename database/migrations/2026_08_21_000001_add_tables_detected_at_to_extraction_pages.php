<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When table detection last ran on a page, whether or not it found anything.
 *
 * Absence of cells meant two different things — "there is no table here" and
 * "nobody has looked" — and the slowest step in the pipeline could not tell
 * them apart. So every run re-derived the negatives: of 451 pages detected
 * overnight, about 297 found no table, and all 297 stayed in the queue of pages
 * "missing structure", ready to be paid for again at up to sixteen minutes
 * each.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_pages', function (Blueprint $table): void {
            $table->timestamp('tables_detected_at')->nullable()->after('recognized_dpi');
            $table->index('tables_detected_at');
        });
    }

    public function down(): void
    {
        Schema::table('extraction_pages', function (Blueprint $table): void {
            $table->dropIndex(['tables_detected_at']);
            $table->dropColumn('tables_detected_at');
        });
    }
};
