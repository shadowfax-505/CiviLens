<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A benchmark evaluation run reads gold-annotated pages from an external corpus,
 * not an acquired source artifact. Forcing it to borrow an artifact row would
 * put a fabricated acquisition record in the provenance ledger, so the link
 * becomes optional and the run records which benchmark it came from instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_runs', function (Blueprint $table): void {
            $table->string('benchmark', 96)->nullable()->after('uuid');
            $table->index(['benchmark', 'completed_at']);
        });

        Schema::table('extraction_runs', function (Blueprint $table): void {
            $table->foreignId('source_artifact_version_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('extraction_runs', function (Blueprint $table): void {
            $table->dropIndex(['benchmark', 'completed_at']);
            $table->dropColumn('benchmark');
        });
    }
};
