<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Names a contractor could be matched against, kept as data rather than
     * parsed out of a CSV on every question.
     *
     * The normalised form is stored beside the name as published. Matching needs
     * the first; a reviewer deciding whether a match is real needs the second,
     * and recomputing it later would silently change what past decisions meant.
     */
    public function up(): void
    {
        Schema::create('screening_entities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_artifact_version_id')->constrained()->cascadeOnDelete();
            $table->string('dataset', 64);
            $table->string('external_id', 128);
            $table->string('entity_type', 32)->default('LegalEntity');
            $table->string('name', 512);
            $table->string('normalized_name', 512)->index();
            $table->json('aliases')->nullable();
            $table->string('countries', 255)->nullable();
            $table->string('topics', 255)->nullable();
            $table->timestamps();

            $table->unique(['dataset', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_entities');
    }
};
