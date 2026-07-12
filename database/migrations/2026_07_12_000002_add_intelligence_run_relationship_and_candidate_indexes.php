<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_indicators', function (Blueprint $table): void {
            $table->foreignId('civic_intelligence_run_id')
                ->nullable()
                ->after('id')
                ->constrained('civic_intelligence_runs')
                ->nullOnDelete();
            $table->index(['civic_intelligence_run_id', 'severity', 'detected_at'], 'intel_indicators_run_severity_detected_idx');
        });

        Schema::table('civic_intelligence_runs', function (Blueprint $table): void {
            $table->index(['status', 'started_at'], 'intel_runs_status_started_idx');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->index(['planned_end_date', 'progress_percentage'], 'projects_intel_delay_candidate_idx');
        });

        Schema::table('awards', function (Blueprint $table): void {
            $table->index(['status', 'bid_submission_id'], 'awards_intel_status_bid_idx');
        });

        Schema::table('citizen_reports', function (Blueprint $table): void {
            $table->index(['resolved_at', 'project_id'], 'citizen_reports_intel_open_project_idx');
        });
    }

    public function down(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table): void {
            $table->dropIndex('citizen_reports_intel_open_project_idx');
        });

        Schema::table('awards', function (Blueprint $table): void {
            $table->dropIndex('awards_intel_status_bid_idx');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex('projects_intel_delay_candidate_idx');
        });

        Schema::table('civic_intelligence_runs', function (Blueprint $table): void {
            $table->dropIndex('intel_runs_status_started_idx');
        });

        Schema::table('intelligence_indicators', function (Blueprint $table): void {
            $table->dropForeign(['civic_intelligence_run_id']);
            $table->dropIndex('intel_indicators_run_severity_detected_idx');
            $table->dropColumn('civic_intelligence_run_id');
        });
    }
};
