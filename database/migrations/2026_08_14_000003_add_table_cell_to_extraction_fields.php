<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which table cell a value was taken from.
 *
 * Set only for values found inside a cell, so provenance is carried rather than
 * inferred. Matching an existing value to a cell by its text was rejected: 81 of
 * 300 sampled candidates appear more than once on their own page, and choosing a
 * cell for a repeated figure would invent the provenance it claims to record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_fields', function (Blueprint $table): void {
            $table->foreignId('extraction_table_cell_id')->nullable()->after('extraction_page_id')
                ->constrained('extraction_table_cells')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('extraction_fields', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('extraction_table_cell_id');
        });
    }
};
