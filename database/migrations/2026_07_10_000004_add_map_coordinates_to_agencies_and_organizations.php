<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->json('geojson')->nullable()->after('longitude');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->decimal('headquarters_latitude', 10, 7)->nullable()->after('headquarters_address');
            $table->decimal('headquarters_longitude', 10, 7)->nullable()->after('headquarters_latitude');
            $table->json('headquarters_geojson')->nullable()->after('headquarters_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['headquarters_latitude', 'headquarters_longitude', 'headquarters_geojson']);
        });

        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude', 'geojson']);
        });
    }
};
