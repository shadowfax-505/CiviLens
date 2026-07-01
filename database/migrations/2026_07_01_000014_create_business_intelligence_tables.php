<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_snapshot_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('analytics_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analytics_snapshot_period_id')->constrained()->restrictOnDelete();
            $table->string('dashboard')->index();
            $table->date('snapshot_date')->index();
            $table->string('filter_hash', 64)->index();
            $table->json('filters')->nullable();
            $table->json('metrics');
            $table->json('charts')->nullable();
            $table->json('insights')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['analytics_snapshot_period_id', 'dashboard', 'snapshot_date'], 'analytics_snapshot_period_dashboard_date_index');
        });

        Schema::create('analytics_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('dashboard')->index();
            $table->string('format', 20)->index();
            $table->string('status')->default('generated')->index();
            $table->json('filters')->nullable();
            $table->json('payload')->nullable();
            $table->string('storage_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_alert_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->string('metric_key')->index();
            $table->string('operator', 8);
            $table->decimal('threshold', 18, 4);
            $table->string('severity')->index();
            $table->text('message_template');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('analytics_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analytics_alert_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('severity')->index();
            $table->string('status')->default('open')->index();
            $table->decimal('triggered_value', 18, 4)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('triggered_at')->index();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['analytics_alert_rule_id', 'status'], 'analytics_alert_rule_status_index');
        });

        Schema::create('dashboard_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('dashboard')->index();
            $table->json('filters')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'dashboard']);
        });

        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->index();
            $table->string('dashboard')->nullable()->index();
            $table->nullableMorphs('subject');
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('dashboard_states');
        Schema::dropIfExists('analytics_alerts');
        Schema::dropIfExists('analytics_alert_rules');
        Schema::dropIfExists('analytics_reports');
        Schema::dropIfExists('analytics_snapshots');
        Schema::dropIfExists('analytics_snapshot_periods');
    }
};
