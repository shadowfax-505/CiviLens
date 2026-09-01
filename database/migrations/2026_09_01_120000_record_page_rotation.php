<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How far a page was turned before it was read.
 *
 * A landscape form printed on a portrait page reaches OCR sideways and comes
 * back as gibberish, so the enhanced pass now straightens it first. The word
 * geometry that pass records is in the *rotated* frame, and anything drawing a
 * crop on the page image afterwards has to turn the page the same way before
 * the boxes mean anything.
 *
 * Null means the page was read as stored, which is every page read before this
 * existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_pages', function (Blueprint $table): void {
            $table->unsignedSmallInteger('rotation_applied')->nullable()->after('recognized_dpi');
        });
    }

    public function down(): void
    {
        Schema::table('extraction_pages', function (Blueprint $table): void {
            $table->dropColumn('rotation_applied');
        });
    }
};
