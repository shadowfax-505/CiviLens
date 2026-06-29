<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('password');
            $table->boolean('is_active')->default(true)->index()->after('avatar_path');
            $table->timestamp('locked_at')->nullable()->index()->after('is_active');
            $table->json('notification_preferences')->nullable()->after('locked_at');
            $table->timestamp('last_login_at')->nullable()->index()->after('remember_token');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_path',
                'is_active',
                'locked_at',
                'notification_preferences',
                'last_login_at',
                'password_changed_at',
            ]);
        });
    }
};
