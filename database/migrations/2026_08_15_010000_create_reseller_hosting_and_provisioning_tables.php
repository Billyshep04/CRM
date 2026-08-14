<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hosting_accounts', function (Blueprint $table): void {
            $table->id(); $table->foreignId('hosting_server_id')->constrained('hosting_servers')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('external_id'); $table->string('username'); $table->string('primary_domain')->nullable(); $table->string('package_name')->nullable();
            $table->string('status')->default('active'); $table->unsignedBigInteger('disk_used_bytes')->nullable(); $table->unsignedBigInteger('disk_limit_bytes')->nullable();
            $table->unsignedBigInteger('bandwidth_used_bytes')->nullable(); $table->json('domains')->nullable(); $table->json('metadata')->nullable(); $table->timestamp('last_synced_at')->nullable(); $table->timestamps();
            $table->unique(['hosting_server_id', 'external_id']); $table->unique(['hosting_server_id', 'username']);
        });
        Schema::create('hosting_packages', function (Blueprint $table): void {
            $table->id(); $table->foreignId('hosting_server_id')->constrained('hosting_servers')->cascadeOnDelete(); $table->string('external_id'); $table->string('name'); $table->json('limits')->nullable(); $table->boolean('active')->default(true); $table->timestamp('last_synced_at')->nullable(); $table->timestamps(); $table->unique(['hosting_server_id', 'external_id']);
        });
        Schema::create('wordpress_profiles', function (Blueprint $table): void {
            $table->id(); $table->string('name')->unique(); $table->string('slug')->unique(); $table->text('description')->nullable(); $table->json('configuration'); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('website_provisioning_runs', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete(); $table->foreignId('hosting_server_id')->nullable()->constrained('hosting_servers')->nullOnDelete(); $table->foreignId('hosting_account_id')->nullable()->constrained('hosting_accounts')->nullOnDelete(); $table->foreignId('hosting_package_id')->nullable()->constrained('hosting_packages')->nullOnDelete(); $table->foreignId('wordpress_profile_id')->nullable()->constrained('wordpress_profiles')->nullOnDelete(); $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key')->unique(); $table->string('domain')->unique(); $table->string('state')->default('pending')->index(); $table->string('mode')->default('mock'); $table->string('website_type')->default('wordpress'); $table->json('options')->nullable(); $table->string('failed_step')->nullable(); $table->text('safe_error')->nullable(); $table->unsignedInteger('attempts')->default(0); $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
        Schema::create('website_provisioning_steps', function (Blueprint $table): void {
            $table->id(); $table->foreignId('website_provisioning_run_id')->constrained('website_provisioning_runs')->cascadeOnDelete(); $table->string('step'); $table->string('status')->default('pending'); $table->unsignedInteger('attempts')->default(0); $table->text('safe_message')->nullable(); $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable(); $table->json('metadata')->nullable(); $table->timestamps(); $table->unique(['website_provisioning_run_id', 'step']);
        });
        Schema::table('websites', function (Blueprint $table): void { $table->foreignId('hosting_account_id')->nullable()->after('hosting_server_id')->constrained('hosting_accounts')->nullOnDelete(); $table->string('provisioning_status')->nullable()->index(); });

        DB::table('websites')->whereNotNull('hosting_server_id')->whereNotNull('cpanel_username')->orderBy('id')->get()->each(function ($website): void {
            $accountId = DB::table('hosting_accounts')->where('hosting_server_id', $website->hosting_server_id)->where('username', $website->cpanel_username)->value('id');
            if (!$accountId) $accountId = DB::table('hosting_accounts')->insertGetId(['hosting_server_id' => $website->hosting_server_id, 'customer_id' => $website->customer_id, 'external_id' => $website->cpanel_username, 'username' => $website->cpanel_username, 'primary_domain' => $website->domain, 'status' => 'legacy_unverified', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('websites')->where('id', $website->id)->update(['hosting_account_id' => $accountId]);
        });
    }
    public function down(): void { Schema::table('websites', function (Blueprint $table): void { $table->dropConstrainedForeignId('hosting_account_id'); $table->dropColumn('provisioning_status'); }); Schema::dropIfExists('website_provisioning_steps'); Schema::dropIfExists('website_provisioning_runs'); Schema::dropIfExists('wordpress_profiles'); Schema::dropIfExists('hosting_packages'); Schema::dropIfExists('hosting_accounts'); }
};
