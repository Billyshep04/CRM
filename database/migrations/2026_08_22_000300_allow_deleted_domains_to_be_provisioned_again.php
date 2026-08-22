<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_provisioning_runs')) return;

        Schema::table('website_provisioning_runs', function (Blueprint $table): void {
            $table->dropUnique(['domain']);
            $table->index('domain');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('website_provisioning_runs')) return;

        Schema::table('website_provisioning_runs', function (Blueprint $table): void {
            $table->dropIndex(['domain']);
            $table->unique('domain');
        });
    }
};
