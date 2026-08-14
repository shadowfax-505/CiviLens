<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What an acceptance actually claims.
     *
     * A field accepted at alpha 0.05 on its own group's threshold and one
     * accepted at 0.20 on a threshold borrowed from every publisher writing its
     * script are not the same statement. Recorded on the field so the two cannot
     * be reported as one later.
     */
    public function up(): void
    {
        Schema::table('extraction_fields', function (Blueprint $table): void {
            $table->string('decision_basis', 32)->nullable()->after('decision_alpha');
        });
    }

    public function down(): void
    {
        Schema::table('extraction_fields', function (Blueprint $table): void {
            $table->dropColumn('decision_basis');
        });
    }
};
