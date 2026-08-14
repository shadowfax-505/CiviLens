<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Word boxes are pixels, and pixels only mean something beside the DPI they
     * were measured at. A page read by the enhanced pass carries boxes at 300
     * while the table assembler renders at 150, and every word then lands in the
     * wrong cell or in none. The DPI is recorded so the assembler can render the
     * page the way the recognizer saw it.
     */
    public function up(): void
    {
        Schema::table('extraction_pages', function (Blueprint $table): void {
            $table->unsignedSmallInteger('recognized_dpi')->nullable()->after('recognized_words');
        });
    }

    public function down(): void
    {
        Schema::table('extraction_pages', function (Blueprint $table): void {
            $table->dropColumn('recognized_dpi');
        });
    }
};
