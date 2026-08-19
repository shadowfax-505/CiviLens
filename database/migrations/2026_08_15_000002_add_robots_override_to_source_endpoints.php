<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An operator's recorded decision to fetch a path robots.txt disallows.
     *
     * A reason rather than a boolean, and per endpoint rather than global. The
     * decision is a legal and editorial one that belongs to the operator, and it
     * has to travel with the source it applies to: a switch that turned robots
     * off everywhere would be indistinguishable from never having implemented
     * it, and nothing in a later write-up could say which sources were taken
     * against a publisher's stated wishes.
     */
    public function up(): void
    {
        Schema::table('source_endpoints', function (Blueprint $table): void {
            $table->text('robots_override_reason')->nullable()->after('access_decision');
            $table->timestamp('robots_override_recorded_at')->nullable()->after('robots_override_reason');
        });
    }

    public function down(): void
    {
        Schema::table('source_endpoints', function (Blueprint $table): void {
            $table->dropColumn(['robots_override_reason', 'robots_override_recorded_at']);
        });
    }
};
