<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 200);
            $table->string('website_url', 2048)->nullable();
            $table->string('normalized_domain', 253)->nullable();
            $table->string('google_place_id', 191)->nullable();
            $table->decimal('google_rating', 3, 2)->nullable();
            $table->unsignedInteger('google_review_count')->nullable();
            $table->date('domain_registered_at')->nullable();
            $table->decimal('design_quality_score', 5, 2)->nullable();
            $table->decimal('professionalism_score', 5, 2)->nullable();
            $table->json('missing_features')->nullable();
            $table->decimal('lead_score', 5, 2)->nullable();
            $table->string('lead_grade', 20)->nullable();
            $table->timestamp('lead_scored_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('google_place_id');
            $table->index('normalized_domain');
            $table->index(['lead_grade', 'lead_score']);
            $table->index(['owner_user_id', 'lead_score']);
            $table->index(['google_rating', 'google_review_count']);
        });

        Schema::table('website_audits', function (Blueprint $table): void {
            $table->foreignId('business_id')->nullable()->after('website_id')->constrained('businesses')->nullOnDelete();
            $table->index(['business_id', 'completed_at']);
            $table->unique(['business_id', 'version']);
        });

        Schema::create('lead_scoring_profiles', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'version']);
            $table->index(['is_default', 'is_active']);
        });

        Schema::create('lead_scoring_weights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_scoring_profile_id')->constrained()->cascadeOnDelete();
            $table->string('factor', 80);
            $table->decimal('weight', 7, 3);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['lead_scoring_profile_id', 'factor']);
            $table->index(['lead_scoring_profile_id', 'is_enabled']);
        });

        Schema::create('lead_scores', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_audit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_scoring_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('calculated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('score', 5, 2);
            $table->string('grade', 20);
            $table->decimal('confidence', 5, 2);
            $table->json('breakdown');
            $table->json('input_snapshot');
            $table->boolean('is_current')->default(true);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['business_id', 'is_current']);
            $table->index(['business_id', 'calculated_at']);
            $table->index(['grade', 'score']);
            $table->index(['lead_scoring_profile_id', 'calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_scores');
        Schema::dropIfExists('lead_scoring_weights');
        Schema::dropIfExists('lead_scoring_profiles');
        Schema::table('website_audits', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'version']);
            $table->dropConstrainedForeignId('business_id');
        });
        Schema::dropIfExists('businesses');
    }
};
