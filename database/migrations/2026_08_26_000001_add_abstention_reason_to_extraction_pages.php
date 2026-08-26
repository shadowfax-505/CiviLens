<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a page was abstained on, as distinct from the fact that it was.
 *
 * Every abstention carried the same sentence — "confidence remained below the
 * acceptance threshold after one enhanced pass" — including 198 pages where the
 * recognizer produced no characters and no confidence at all, because the page
 * is blank. Those are opposite findings. One says the system could not read a
 * page; the other says there was nothing on it, and a blank separator page
 * abstained on is the system working.
 *
 * Reported as one rate, an abstention figure of 23% reads as a claim about how
 * much of the corpus defeats the reader. It is not, and a write-up that quoted
 * it that way would be overstating the difficulty and understating the method.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_pages', function (Blueprint $table): void {
            $table->string('abstention_reason')->nullable()->after('failure_reason');
            $table->float('ink_coverage')->nullable()->after('abstention_reason');
            $table->index('abstention_reason');
        });
    }

    public function down(): void
    {
        Schema::table('extraction_pages', function (Blueprint $table): void {
            $table->dropIndex(['abstention_reason']);
            $table->dropColumn(['abstention_reason', 'ink_coverage']);
        });
    }
};
