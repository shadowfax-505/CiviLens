<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A new endpoint may fetch what these publishers actually issue.
 *
 * The per-endpoint default was 20 MB while the absolute ceiling — the real
 * safety bound, applied to every fetch regardless — is 50 MB. Bangladeshi audit
 * reports run right up to that lower figure: the largest document accepted is
 * 19.9 MB, which is what a distribution truncated at 20 looks like, and one
 * refused document is a 46 MB audit report that the ceiling would have allowed.
 *
 * The limit still does its job at the top. Four of the refusals are one 529 MB
 * scan of a handwritten constitution, which is a historical artefact rather
 * than a financial record and is beyond the ceiling in any case.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_endpoints', function (Blueprint $table): void {
            $table->unsignedBigInteger('max_content_bytes')->default(52428800)->change();
        });
    }

    public function down(): void
    {
        Schema::table('source_endpoints', function (Blueprint $table): void {
            $table->unsignedBigInteger('max_content_bytes')->default(20971520)->change();
        });
    }
};
