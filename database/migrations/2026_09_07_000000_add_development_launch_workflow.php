<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->string('development_domain')->nullable()->after('domain')->index();
            $table->string('production_domain')->nullable()->after('development_domain')->index();
            $table->string('current_domain')->nullable()->after('production_domain')->index();
            $table->timestamp('went_live_at')->nullable()->after('current_domain');
        });

        DB::table('websites')->orderBy('id')->eachById(function ($website): void {
            DB::table('websites')->where('id', $website->id)->update([
                'environment' => 'production',
                'production_domain' => $website->domain,
                'current_domain' => $website->domain,
            ]);
        });

        Schema::table('hosting_accounts', function (Blueprint $table): void {
            $table->boolean('provider_missing')->default(false)->after('status')->index();
            $table->timestamp('provider_last_seen_at')->nullable()->after('last_synced_at');
            $table->timestamp('provider_missing_at')->nullable()->after('provider_last_seen_at');
            $table->text('automation_password_encrypted')->nullable()->after('provider_missing_at');
        });

        Schema::create('website_launch_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hosting_account_id')->constrained('hosting_accounts')->restrictOnDelete();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('development_domain');
            $table->string('production_domain')->index();
            $table->string('state')->default('pending')->index();
            $table->string('failed_step')->nullable();
            $table->text('safe_error')->nullable();
            $table->string('expected_ip')->nullable();
            $table->string('dns_provider')->nullable();
            $table->json('dns_status')->nullable();
            $table->json('ssl_status')->nullable();
            $table->json('options')->nullable();
            $table->text('recovery_encrypted')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('website_launch_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_launch_run_id')->constrained('website_launch_runs')->cascadeOnDelete();
            $table->string('step');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('safe_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['website_launch_run_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_launch_steps');
        Schema::dropIfExists('website_launch_runs');
        Schema::table('hosting_accounts', fn (Blueprint $table) => $table->dropIndex(['provider_missing']));
        Schema::table('hosting_accounts', fn (Blueprint $table) => $table->dropColumn(['provider_missing', 'provider_last_seen_at', 'provider_missing_at', 'automation_password_encrypted']));
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropIndex(['development_domain']);
            $table->dropIndex(['production_domain']);
            $table->dropIndex(['current_domain']);
        });
        Schema::table('websites', fn (Blueprint $table) => $table->dropColumn(['development_domain', 'production_domain', 'current_domain', 'went_live_at']));
    }
};
