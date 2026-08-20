<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->string('lifecycle_state')->default('active')->after('provisioning_status');
            $table->string('deletion_status')->nullable()->after('lifecycle_state');
            $table->boolean('monitoring_enabled')->default(false)->after('management_enabled');
        });
        Schema::table('hosting_accounts', function (Blueprint $table): void {
            $table->unsignedBigInteger('bandwidth_limit_bytes')->nullable();
            $table->unsignedBigInteger('inode_used')->nullable();
            $table->unsignedBigInteger('inode_limit')->nullable();
            $table->unsignedInteger('database_count')->nullable();
            $table->unsignedBigInteger('database_usage_bytes')->nullable();
            $table->unsignedInteger('mailbox_count')->nullable();
            $table->unsignedBigInteger('mailbox_usage_bytes')->nullable();
            $table->string('php_version')->nullable();
            $table->string('ssl_status')->nullable();
            $table->string('ssl_issuer')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->json('resource_limits')->nullable();
            $table->json('dns')->nullable();
            $table->timestamp('provider_created_at')->nullable();
            $table->timestamp('last_metrics_synced_at')->nullable();
        });
        DB::table('websites')->whereNotNull('agent_last_seen_at')->update(['monitoring_enabled'=>true]);
        Schema::create('hosting_metric_snapshots', function (Blueprint $table): void {
            $table->id(); $table->foreignId('hosting_account_id')->constrained()->cascadeOnDelete(); $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('unknown'); $table->unsignedBigInteger('disk_used_bytes')->nullable(); $table->unsignedBigInteger('disk_limit_bytes')->nullable();
            $table->unsignedBigInteger('bandwidth_used_bytes')->nullable(); $table->unsignedBigInteger('bandwidth_limit_bytes')->nullable(); $table->unsignedBigInteger('inode_used')->nullable(); $table->unsignedBigInteger('inode_limit')->nullable();
            $table->json('metrics')->nullable(); $table->timestamp('captured_at')->index(); $table->timestamps();
        });
        Schema::create('website_credentials', function (Blueprint $table): void {
            $table->id(); $table->foreignId('website_id')->constrained()->cascadeOnDelete(); $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('wordpress_admin'); $table->string('username'); $table->text('secret_encrypted'); $table->timestamp('revealed_at')->nullable(); $table->timestamp('revoked_at')->nullable(); $table->timestamps();
            $table->unique(['website_id', 'type']);
        });
        Schema::create('website_deletion_audits', function (Blueprint $table): void {
            $table->id(); $table->uuid('idempotency_key')->unique(); $table->unsignedBigInteger('website_id')->nullable()->index(); $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('hosting_server_id')->nullable()->index(); $table->unsignedBigInteger('hosting_account_id')->nullable()->index(); $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('website_name'); $table->string('domain'); $table->string('customer_name')->nullable(); $table->string('hosting_provider')->nullable(); $table->string('hosting_server_name')->nullable(); $table->string('cpanel_username')->nullable();
            $table->string('deletion_type'); $table->string('state')->default('requested')->index(); $table->text('safe_error')->nullable(); $table->json('metadata')->nullable(); $table->timestamp('requested_at'); $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('website_deletion_audits'); Schema::dropIfExists('website_credentials'); Schema::dropIfExists('hosting_metric_snapshots');
        Schema::table('hosting_accounts', fn (Blueprint $table) => $table->dropColumn(['bandwidth_limit_bytes','inode_used','inode_limit','database_count','database_usage_bytes','mailbox_count','mailbox_usage_bytes','php_version','ssl_status','ssl_issuer','ssl_expires_at','resource_limits','dns','provider_created_at','last_metrics_synced_at']));
        Schema::table('websites', fn (Blueprint $table) => $table->dropColumn(['lifecycle_state','deletion_status','monitoring_enabled']));
    }
};
