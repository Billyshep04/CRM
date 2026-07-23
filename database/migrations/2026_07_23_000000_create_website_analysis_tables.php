<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_audits', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('website_id')->nullable()->constrained('websites')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 30)->default('pending');
            $table->string('requested_url', 2048);
            $table->string('final_url', 2048)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('http_version', 20)->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->decimal('seo_score', 5, 2)->nullable();
            $table->decimal('performance_score', 5, 2)->nullable();
            $table->decimal('accessibility_score', 5, 2)->nullable();
            $table->decimal('security_score', 5, 2)->nullable();
            $table->json('redirect_chain')->nullable();
            $table->json('structured_results')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'version']);
            $table->index(['status', 'created_at']);
            $table->index(['requested_by_user_id', 'created_at']);
            $table->index(['website_id', 'completed_at']);
        });

        Schema::create('seo_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_audit_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->string('meta_title', 500)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->unsignedInteger('heading_count')->default(0);
            $table->unsignedInteger('image_count')->default(0);
            $table->unsignedInteger('images_missing_alt')->default(0);
            $table->unsignedInteger('internal_link_count')->default(0);
            $table->unsignedInteger('broken_link_count')->default(0);
            $table->boolean('has_sitemap')->default(false);
            $table->boolean('has_robots_txt')->default(false);
            $table->unsignedInteger('schema_item_count')->default(0);
            $table->json('details');
            $table->timestamps();

            $table->index(['score', 'broken_link_count']);
        });

        Schema::create('performance_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_audit_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->unsignedBigInteger('page_size_bytes')->default(0);
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->json('details');
            $table->timestamps();

            $table->index(['score', 'page_size_bytes']);
        });

        Schema::create('accessibility_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_audit_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->unsignedInteger('images_missing_alt')->default(0);
            $table->unsignedInteger('empty_link_count')->default(0);
            $table->unsignedInteger('unlabelled_form_control_count')->default(0);
            $table->string('html_language', 35)->nullable();
            $table->json('details');
            $table->timestamps();

            $table->index(['score', 'images_missing_alt']);
        });

        Schema::create('security_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_audit_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->boolean('uses_https')->default(false);
            $table->boolean('ssl_valid')->default(false);
            $table->string('server_technology', 255)->nullable();
            $table->string('hosting_provider', 255)->nullable();
            $table->boolean('has_hsts')->default(false);
            $table->boolean('has_csp')->default(false);
            $table->boolean('has_frame_protection')->default(false);
            $table->json('details');
            $table->timestamps();

            $table->index(['score', 'uses_https']);
        });

        Schema::create('audit_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_audit_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('check_key', 150);
            $table->string('severity', 20);
            $table->string('status', 20);
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('evidence')->nullable();
            $table->text('recommendation')->nullable();
            $table->timestamps();

            $table->unique(['website_audit_id', 'check_key']);
            $table->index(['website_audit_id', 'category', 'severity']);
            $table->index(['check_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('security_audits');
        Schema::dropIfExists('accessibility_audits');
        Schema::dropIfExists('performance_audits');
        Schema::dropIfExists('seo_audits');
        Schema::dropIfExists('website_audits');
    }
};
