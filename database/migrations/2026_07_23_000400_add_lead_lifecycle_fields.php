<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->timestamp('contacted_at')->nullable()->after('last_discovered_at');
            $table->index(['contacted_at', 'created_at']);
        });

        Schema::table('lead_discovery_runs', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropIndex(['contacted_at', 'created_at']);
            $table->dropColumn('contacted_at');
        });
        Schema::table('lead_discovery_runs', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
