<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_rules', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('category');
            $table->unsignedSmallInteger('priority')->default(100)->after('severity_default')->index();
            $table->unsignedTinyInteger('weight')->default(50)->after('priority');
            $table->string('execution_frequency')->default('manual')->after('configuration')->index();
            $table->string('documentation_url')->nullable()->after('execution_frequency');
            $table->timestamp('last_executed_at')->nullable()->after('documentation_url')->index();
            $table->unsignedInteger('last_execution_ms')->nullable()->after('last_executed_at');
        });

        Schema::create('intelligence_rule_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intelligence_rule_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->index();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['intelligence_rule_id', 'occurred_at'], 'intel_rule_audits_rule_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_rule_audits');

        Schema::table('intelligence_rules', function (Blueprint $table): void {
            $table->dropColumn([
                'description',
                'priority',
                'weight',
                'execution_frequency',
                'documentation_url',
                'last_executed_at',
                'last_execution_ms',
            ]);
        });
    }
};
