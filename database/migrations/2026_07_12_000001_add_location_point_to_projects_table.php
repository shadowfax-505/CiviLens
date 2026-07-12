<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->spatialIndex('location');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropSpatialIndex(['location']);
            $table->dropColumn('location');
        });
    }
};
