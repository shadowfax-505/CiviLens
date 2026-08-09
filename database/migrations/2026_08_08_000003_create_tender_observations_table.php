<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a publisher said about a tender at a point in time.
 *
 * Deliberately separate from the operational `tenders` table. That table holds
 * records CivicLens owns; this holds observations of someone else's record,
 * which may be incomplete, may contradict an earlier observation, and are not
 * authoritative. Merging them would make a publisher's claim indistinguishable
 * from a verified fact.
 *
 * Rows are append-only per observation so a value changing over time is
 * visible as history rather than overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_publisher_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('discovered_resource_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id', 64);
            $table->string('reference_number', 300)->nullable();
            $table->string('status', 64)->nullable();
            $table->string('procurement_nature', 96)->nullable();
            $table->string('published_on_raw', 64)->nullable();
            $table->date('published_on')->nullable();
            $table->char('observation_hash', 64);
            $table->timestamp('observed_at');
            $table->timestamps();

            // One row per distinct observation. Re-seeing an unchanged notice
            // updates nothing; seeing a changed one records a new observation.
            $table->unique(['source_publisher_id', 'external_id', 'observation_hash'], 'tender_observation_unique');
            $table->index(['source_publisher_id', 'external_id', 'observed_at']);
            $table->index(['procurement_nature', 'published_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_observations');
    }
};
