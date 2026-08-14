<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_health_checks', function (Blueprint $table): void {
            $table->unsignedInteger('plugin_count')->nullable()->after('php_version');
        });
        Schema::table('websites', function (Blueprint $table): void {
            $table->string('google_analytics_property_id')->nullable()->after('cpanel_username');
            $table->string('google_analytics_dashboard_url', 2048)->nullable()->after('google_analytics_property_id');
        });
    }

    public function down(): void
    {
        Schema::table('website_health_checks', fn (Blueprint $table) => $table->dropColumn('plugin_count'));
        Schema::table('websites', fn (Blueprint $table) => $table->dropColumn(['google_analytics_property_id', 'google_analytics_dashboard_url']));
    }
};
