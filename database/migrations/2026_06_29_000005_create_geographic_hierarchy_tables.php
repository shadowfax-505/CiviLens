<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('iso2', 2)->unique();
            $table->string('iso3', 3)->unique();
            $table->string('phone_code', 16)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('geojson')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('name');
            $table->index(['name', 'iso2']);
        });

        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('geojson')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['country_id', 'name']);
            $table->index(['country_id', 'code']);
        });

        Schema::create('districts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('geojson')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['division_id', 'name']);
            $table->index(['division_id', 'code']);
        });

        Schema::create('upazilas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('geojson')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['district_id', 'name']);
            $table->index(['district_id', 'code']);
        });

        Schema::create('unions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('upazila_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('type')->default('union');
            $table->string('code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('geojson')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['upazila_id', 'name']);
            $table->index(['upazila_id', 'type']);
            $table->index(['upazila_id', 'code']);
        });

        Schema::create('wards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('union_id')->constrained('unions')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('geojson')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['union_id', 'name']);
            $table->index(['union_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wards');
        Schema::dropIfExists('unions');
        Schema::dropIfExists('upazilas');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('countries');
    }
};
