<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->geometry('location', 'point', 4326)->nullable()->after('longitude');
            $table->index(['latitude', 'longitude', 'id']);
        });

        DB::statement(
            'UPDATE projects SET location = ST_SRID(POINT(longitude, latitude), 4326) WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180',
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['latitude', 'longitude', 'id']);
            $table->dropColumn('location');
        });
    }
};
