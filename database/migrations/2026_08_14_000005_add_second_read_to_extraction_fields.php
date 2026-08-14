<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the second read saw, so a score can be audited rather than trusted.
     *
     * A nonconformity score is a number nobody can check. Keeping the reading it
     * came from, and the confidence that reading carried, makes a surprising
     * score explainable instead of mysterious.
     */
    public function up(): void
    {
        Schema::table('extraction_fields', function (Blueprint $table): void {
            $table->string('second_read_value', 255)->nullable()->after('normalized_value');
            $table->float('second_read_confidence')->nullable()->after('second_read_value');
            $table->string('score_basis', 32)->nullable()->after('nonconformity_score');
        });
    }

    public function down(): void
    {
        Schema::table('extraction_fields', function (Blueprint $table): void {
            $table->dropColumn(['second_read_value', 'second_read_confidence', 'score_basis']);
        });
    }
};
