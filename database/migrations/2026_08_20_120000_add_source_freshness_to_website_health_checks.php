<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_health_checks', function (Blueprint $table): void {
            $table->timestamp('availability_checked_at')->nullable()->index()->after('checked_at');
            $table->timestamp('ssl_checked_at')->nullable()->index()->after('ssl_expires_at');
            $table->unsignedSmallInteger('ssl_days_remaining')->nullable()->after('ssl_checked_at');
            $table->string('ssl_error_reason', 80)->nullable()->after('ssl_days_remaining');
            $table->timestamp('wordpress_checked_at')->nullable()->index()->after('site_health_status');
            $table->timestamp('backup_checked_at')->nullable()->index()->after('backup_status');
            $table->timestamp('performance_checked_at')->nullable()->index()->after('performance_score');
            $table->timestamp('hosting_synced_at')->nullable()->index()->after('performance_checked_at');
        });

        DB::table('website_health_checks')->where('check_type', '!=', 'agent')->update(['availability_checked_at' => DB::raw('checked_at')]);
        DB::table('website_health_checks')->whereNotIn('ssl_status', ['unknown', ''])->whereNotNull('ssl_status')->update(['ssl_checked_at' => DB::raw('checked_at')]);
        DB::table('website_health_checks')->where(function ($query): void {
            $query->whereNotNull('wordpress_version')->orWhereNotNull('plugin_count');
        })->update(['wordpress_checked_at' => DB::raw('checked_at')]);
        DB::table('website_health_checks')->where(function ($query): void {
            $query->whereNotNull('last_successful_backup_at')->orWhereNotIn('backup_status', ['unknown', '']);
        })->update(['backup_checked_at' => DB::raw('checked_at')]);
    }

    public function down(): void
    {
        Schema::table('website_health_checks', function (Blueprint $table): void {
            $table->dropColumn([
                'availability_checked_at', 'ssl_checked_at', 'ssl_days_remaining',
                'ssl_error_reason', 'wordpress_checked_at', 'backup_checked_at',
                'performance_checked_at', 'hosting_synced_at',
            ]);
        });
    }
};
