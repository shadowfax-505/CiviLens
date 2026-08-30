<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permission for one endpoint to be fetched over plaintext HTTP.
 *
 * The guard allows HTTPS only, which is right: this project's whole value is
 * that a published figure can be traced to a document whose bytes are what the
 * publisher served. Over HTTP those bytes can be altered in transit, and a
 * checksum of altered bytes is a checksum of the alteration.
 *
 * One source needs it. `bdlaws.minlaw.gov.bd` — the government's legal database,
 * holding the Constitution and every Act — answers 200 over HTTP and refuses
 * HTTPS entirely. Without the exception the statutes a judgment cites cannot be
 * resolved at all.
 *
 * Per endpoint, with a reason and a timestamp, on the pattern already set for
 * the robots override. A global relaxation would silently weaken provenance for
 * every source, including the ones that do serve TLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_endpoints', function (Blueprint $table): void {
            $table->text('plaintext_override_reason')->nullable()->after('robots_override_recorded_at');
            $table->timestamp('plaintext_override_recorded_at')->nullable()->after('plaintext_override_reason');
        });
    }

    public function down(): void
    {
        Schema::table('source_endpoints', function (Blueprint $table): void {
            $table->dropColumn(['plaintext_override_reason', 'plaintext_override_recorded_at']);
        });
    }
};
