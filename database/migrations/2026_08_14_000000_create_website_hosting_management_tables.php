<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_servers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('provider')->default('mock');
            $table->string('hostname')->nullable();
            $table->string('server_type')->nullable();
            $table->string('api_type')->default('mock');
            $table->text('credentials')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('websites', function (Blueprint $table): void {
            $table->foreignId('hosting_server_id')->nullable()->after('customer_id')->constrained('hosting_servers')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->after('hosting_server_id')->constrained('subscriptions')->nullOnDelete();
            $table->string('domain')->nullable()->after('name')->index();
            $table->string('environment')->default('production')->after('login_url');
            $table->string('cpanel_username')->nullable()->after('environment');
            $table->boolean('wordpress_enabled')->default(false);
            $table->boolean('management_enabled')->default(false);
            $table->boolean('hosting_enabled')->default(false);
            $table->string('status')->default('unknown')->index();
            $table->string('agent_token_hash', 64)->nullable()->unique();
            $table->text('agent_token_encrypted')->nullable();
            $table->timestamp('agent_last_seen_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedTinyInteger('consecutive_failures')->default(0);
            $table->json('portal_visibility')->nullable();
            $table->json('metadata')->nullable();
        });

        Schema::create('website_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->timestamp('checked_at')->index();
            $table->string('check_type')->default('full')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('uptime_status')->default('unknown');
            $table->string('ssl_status')->default('unknown');
            $table->dateTime('ssl_expires_at')->nullable();
            $table->string('dns_status')->default('unknown');
            $table->string('wordpress_version')->nullable();
            $table->string('php_version')->nullable();
            $table->unsignedInteger('plugin_updates')->nullable();
            $table->unsignedInteger('theme_updates')->nullable();
            $table->unsignedBigInteger('database_size_bytes')->nullable();
            $table->unsignedBigInteger('disk_used_bytes')->nullable();
            $table->unsignedBigInteger('disk_limit_bytes')->nullable();
            $table->unsignedBigInteger('bandwidth_used_bytes')->nullable();
            $table->string('site_health_status')->nullable();
            $table->timestamp('last_successful_backup_at')->nullable();
            $table->string('backup_status')->default('unknown');
            $table->unsignedTinyInteger('performance_score')->nullable();
            $table->string('overall_status')->default('unknown')->index();
            $table->json('warnings')->nullable();
            $table->json('errors')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();
            $table->index(['website_id', 'checked_at']);
        });

        Schema::create('website_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->string('type');
            $table->string('severity')->default('warning');
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('deduplication_key');
            $table->timestamp('opened_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['website_id', 'resolved_at']);
            $table->unique(['website_id', 'deduplication_key', 'resolved_at'], 'website_incident_dedupe');
        });

        Schema::create('website_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('performed_at');
            $table->boolean('visible_to_customer')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['website_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_activities');
        Schema::dropIfExists('website_incidents');
        Schema::dropIfExists('website_health_checks');
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hosting_server_id');
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn(['domain', 'environment', 'cpanel_username', 'wordpress_enabled', 'management_enabled', 'hosting_enabled', 'status', 'agent_token_hash', 'agent_token_encrypted', 'agent_last_seen_at', 'last_checked_at', 'consecutive_failures', 'portal_visibility', 'metadata']);
        });
        Schema::dropIfExists('hosting_servers');
    }
};
