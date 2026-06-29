<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('agencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('agencies')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('agency_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('upazila_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('union_id')->nullable()->constrained('unions')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_type_id', 'status']);
            $table->index(['parent_id', 'status']);
            $table->index(['country_id', 'division_id', 'district_id']);
            $table->index(['upazila_id', 'union_id', 'ward_id']);
            $table->index('name');
        });

        Schema::create('agency_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('relationship')->default('member');
            $table->timestamps();

            $table->unique(['agency_id', 'user_id']);
            $table->index(['user_id', 'relationship']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_user');
        Schema::dropIfExists('agencies');
        Schema::dropIfExists('agency_types');
    }
};
