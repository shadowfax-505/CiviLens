<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An immutable ledger of extraction configurations and what they measured.
 *
 * The improvement loop writes candidates here and never anywhere else. Promotion
 * is a separate human act recorded by promoted_at and promoted_by, so a loop
 * cannot change what production runs by iterating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_experiments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('loop', 64);
            $table->string('corpus', 96);
            $table->unsignedInteger('iteration');
            $table->char('config_hash', 64);
            $table->json('config');
            $table->string('status', 32)->default('running');
            $table->unsignedInteger('fields')->default(0);
            $table->unsignedInteger('correct')->default(0);
            $table->decimal('field_accuracy', 8, 6)->nullable();
            $table->json('metrics')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->foreignId('promoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['loop', 'corpus', 'iteration'], 'extraction_experiment_loop_iteration_unique');
            $table->index(['loop', 'field_accuracy']);
            $table->index(['corpus', 'promoted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_experiments');
    }
};
