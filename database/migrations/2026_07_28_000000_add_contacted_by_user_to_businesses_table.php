<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('businesses') || Schema::hasColumn('businesses', 'contacted_by_user_id')) {
            return;
        }

        Schema::table('businesses', function (Blueprint $table): void {
            $table->foreignId('contacted_by_user_id')
                ->nullable()
                ->after('contacted_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('businesses') || !Schema::hasColumn('businesses', 'contacted_by_user_id')) {
            return;
        }

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contacted_by_user_id');
        });
    }
};
