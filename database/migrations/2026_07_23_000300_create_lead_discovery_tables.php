<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_discovery_runs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 50);
            $table->string('query', 200);
            $table->string('location', 200);
            $table->unsignedSmallInteger('requested_limit')->default(20);
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('results_found')->default(0);
            $table->unsignedInteger('leads_created')->default(0);
            $table->unsignedInteger('leads_updated')->default(0);
            $table->unsignedInteger('duplicates_skipped')->default(0);
            $table->boolean('auto_audit')->default(true);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['requested_by_user_id', 'created_at']);
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->foreignId('lead_discovery_run_id')->nullable()->after('owner_user_id')->constrained()->nullOnDelete();
            $table->string('status', 30)->default('new')->after('name');
            $table->string('source', 50)->default('manual')->after('status');
            $table->string('phone', 80)->nullable()->after('website_url');
            $table->text('address')->nullable()->after('phone');
            $table->string('google_maps_url', 2048)->nullable()->after('google_place_id');
            $table->string('primary_category', 120)->nullable()->after('google_maps_url');
            $table->decimal('latitude', 10, 7)->nullable()->after('primary_category');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->timestamp('discovered_at')->nullable()->after('lead_scored_at');
            $table->timestamp('last_discovered_at')->nullable()->after('discovered_at');
            $table->index(['status', 'created_at']);
            $table->index(['source', 'last_discovered_at']);
            $table->index(['lead_discovery_run_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lead_discovery_run_id');
            $table->dropColumn(['status', 'source', 'phone', 'address', 'google_maps_url', 'primary_category', 'latitude', 'longitude', 'discovered_at', 'last_discovered_at']);
        });
        Schema::dropIfExists('lead_discovery_runs');
    }
};
