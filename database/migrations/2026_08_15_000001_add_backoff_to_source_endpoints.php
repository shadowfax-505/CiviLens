<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Somewhere to record that a publisher has stopped answering.
     *
     * An endpoint marked failing was dispatched again on the next tick and every
     * queued resource retried, so a publisher that refused one request received
     * a hundred more. Ninety-three failed jobs accumulated that way against a
     * host that now refuses the connection outright.
     */
    public function up(): void
    {
        Schema::table('source_endpoints', function (Blueprint $table): void {
            $table->unsignedInteger('failure_streak')->default(0)->after('health_status');
            $table->timestamp('backoff_until')->nullable()->after('failure_streak');
        });
    }

    public function down(): void
    {
        Schema::table('source_endpoints', function (Blueprint $table): void {
            $table->dropColumn(['failure_streak', 'backoff_until']);
        });
    }
};
