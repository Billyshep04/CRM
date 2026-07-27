<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('user_preferences', 'dashboard_tiles')) {
            Schema::table('user_preferences', function (Blueprint $table): void {
                $table->json('dashboard_tiles')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_preferences', 'dashboard_tiles')) {
            Schema::table('user_preferences', function (Blueprint $table): void {
                $table->dropColumn('dashboard_tiles');
            });
        }
    }
};
