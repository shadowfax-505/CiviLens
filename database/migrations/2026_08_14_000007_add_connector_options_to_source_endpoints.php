<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-connector settings that do not belong to every connector.
     *
     * An API feed needs to know how a publisher paginates — which parameter
     * carries the page, whether it counts pages or offsets, how many records a
     * page holds. A column each would be a column each for every connector that
     * ever needs one.
     */
    public function up(): void
    {
        Schema::table('source_endpoints', function (Blueprint $table): void {
            $table->json('connector_options')->nullable()->after('connector_type');
        });
    }

    public function down(): void
    {
        Schema::table('source_endpoints', function (Blueprint $table): void {
            $table->dropColumn('connector_options');
        });
    }
};
