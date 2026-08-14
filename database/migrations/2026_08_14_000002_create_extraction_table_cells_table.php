<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each table cell sits on a page, and what text falls inside it.
 *
 * A figure without its row and column is unusable: a reviewer cannot say which
 * year a number belongs to, and no indicator can be built on it. The structure
 * comes from the sidecar and the text from Tesseract words placed into these
 * boxes, so a cell records both where it is and what was read there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_table_cells', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('extraction_page_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('table_index');
            $table->unsignedSmallInteger('row_index');
            $table->unsignedSmallInteger('column_index');
            $table->unsignedSmallInteger('row_span')->default(1);
            $table->unsignedSmallInteger('column_span')->default(1);
            $table->unsignedInteger('box_left');
            $table->unsignedInteger('box_top');
            $table->unsignedInteger('box_right');
            $table->unsignedInteger('box_bottom');
            // Assembled from recognized words, not from the structure model.
            $table->text('text')->nullable();
            $table->unsignedSmallInteger('word_count')->default(0);
            $table->timestamps();

            $table->unique(['extraction_page_id', 'table_index', 'row_index', 'column_index'], 'extraction_table_cell_position_unique');
            $table->index(['extraction_page_id', 'table_index', 'row_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_table_cells');
    }
};
