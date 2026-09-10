<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            if (! Schema::hasColumn('websites', 'google_analytics_enabled')) {
                $table->boolean('google_analytics_enabled')->default(false)->after('google_analytics_dashboard_url');
            }
            if (! Schema::hasColumn('websites', 'google_analytics_status')) {
                $table->string('google_analytics_status', 20)->nullable()->after('google_analytics_enabled');
            }
            if (! Schema::hasColumn('websites', 'google_analytics_last_synced_at')) {
                $table->timestamp('google_analytics_last_synced_at')->nullable()->after('google_analytics_status');
            }
            if (! Schema::hasColumn('websites', 'google_analytics_last_error')) {
                $table->string('google_analytics_last_error', 500)->nullable()->after('google_analytics_last_synced_at');
            }
        });

        if (! Schema::hasTable('website_analytics_snapshots')) {
            Schema::create('website_analytics_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('property_id');
                $table->string('granularity', 10)->default('day');
                $table->date('period_start');
                $table->date('period_end');
                $table->unsignedBigInteger('sessions')->default(0);
                $table->unsignedBigInteger('total_users')->default(0);
                $table->unsignedBigInteger('new_users')->default(0);
                $table->unsignedBigInteger('engaged_sessions')->default(0);
                $table->unsignedBigInteger('screen_page_views')->default(0);
                $table->unsignedInteger('avg_engagement_time_seconds')->default(0);
                $table->decimal('engagement_rate', 6, 4)->default(0);
                $table->decimal('bounce_rate', 6, 4)->default(0);
                $table->unsignedBigInteger('event_count')->default(0);
                $table->unsignedBigInteger('conversions')->default(0);
                $table->json('key_events')->nullable();
                $table->json('source_data')->nullable();
                $table->timestamp('fetched_at')->nullable();
                $table->timestamps();

                $table->unique(['website_id', 'granularity', 'period_start']);
                $table->index(['website_id', 'granularity', 'period_start', 'period_end'], 'wa_snapshots_range_index');
            });
        }

        if (! Schema::hasTable('website_analytics_dimension_rows')) {
            Schema::create('website_analytics_dimension_rows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('website_analytics_snapshot_id')->constrained('website_analytics_snapshots')->cascadeOnDelete();
                $table->string('dimension', 40);
                $table->string('dimension_value', 512);
                $table->unsignedBigInteger('sessions')->default(0);
                $table->unsignedBigInteger('total_users')->default(0);
                $table->unsignedBigInteger('screen_page_views')->default(0);
                $table->unsignedBigInteger('conversions')->default(0);
                $table->timestamps();

                $table->index(['website_analytics_snapshot_id', 'dimension'], 'wa_dimension_rows_lookup_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website_analytics_dimension_rows');
        Schema::dropIfExists('website_analytics_snapshots');

        Schema::table('websites', function (Blueprint $table): void {
            foreach ([
                'google_analytics_enabled',
                'google_analytics_status',
                'google_analytics_last_synced_at',
                'google_analytics_last_error',
            ] as $column) {
                if (Schema::hasColumn('websites', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
