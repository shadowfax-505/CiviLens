<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confidence is a percentage, and the column said it could not exceed 9.9999.
 *
 * `decimal(5, 4)` permits four decimal places and one digit before the point.
 * OCR confidence runs 0 to 100 and the corpus holds values up to 96.6511, so
 * 16,096 rows carry numbers the schema declares impossible.
 *
 * SQLite stores what it is given and ignores the declared precision entirely,
 * so this was invisible for the life of the project. PostgreSQL enforces it:
 * every one of those rows raises `numeric field overflow`, and 81 of the 684
 * tests fail the moment the suite runs against it. A migration to PostgreSQL —
 * which is M11's whole purpose — would have failed on the real data.
 *
 * Widened rather than rescaled. Rewriting 16,096 confidences into 0–1 would
 * change what every stored number means, and the threshold, the reports and the
 * calibration all read them as percentages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_pages', function (Blueprint $table): void {
            $table->decimal('confidence', 7, 4)->nullable()->change();
        });

        Schema::table('extraction_fields', function (Blueprint $table): void {
            $table->decimal('confidence', 7, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Deliberately not narrowed again. Going back would truncate every
        // confidence above 9.9999, which is almost all of them.
    }
};
