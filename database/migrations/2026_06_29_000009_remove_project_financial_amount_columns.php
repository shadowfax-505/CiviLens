<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['approved_budget', 'progress_percentage']);
            $table->dropColumn(['estimated_budget', 'approved_budget', 'spent_amount']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->decimal('estimated_budget', 15, 2)->default(0)->after('ward_id');
            $table->decimal('approved_budget', 15, 2)->default(0)->after('estimated_budget');
            $table->decimal('spent_amount', 15, 2)->default(0)->after('approved_budget');
        });
    }
};
