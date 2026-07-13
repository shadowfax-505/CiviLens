<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table): void {
            $table->string('attachment_disk')->nullable()->after('contact_preference');
            $table->string('attachment_path')->nullable()->after('attachment_disk');
            $table->string('attachment_original_filename')->nullable()->after('attachment_path');
            $table->string('attachment_mime_type')->nullable()->after('attachment_original_filename');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table): void {
            $table->dropColumn([
                'attachment_disk',
                'attachment_path',
                'attachment_original_filename',
                'attachment_mime_type',
                'attachment_size',
            ]);
        });
    }
};
