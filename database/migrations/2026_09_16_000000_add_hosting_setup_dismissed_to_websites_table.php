<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            if (! Schema::hasColumn('websites', 'hosting_setup_dismissed')) {
                $table->boolean('hosting_setup_dismissed')->default(false)->after('hosting_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            if (Schema::hasColumn('websites', 'hosting_setup_dismissed')) {
                $table->dropColumn('hosting_setup_dismissed');
            }
        });
    }
};
