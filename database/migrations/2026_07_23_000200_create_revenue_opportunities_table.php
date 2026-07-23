<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('converted_job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->string('type', 50);
            $table->string('status', 30)->default('identified');
            $table->string('title', 200);
            $table->text('recommendation')->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 50)->default('manual');
            $table->string('fingerprint', 191)->nullable()->unique();
            $table->unsignedTinyInteger('confidence')->default(50);
            $table->decimal('estimated_project_value', 12, 2)->default(0);
            $table->decimal('estimated_monthly_revenue', 12, 2)->default(0);
            $table->date('renewal_due_at')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'next_action_at']);
            $table->index(['type', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['owner_user_id', 'status']);
            $table->index('renewal_due_at');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('revenue_opportunity_id')->nullable()->after('job_id')->constrained()->nullOnDelete();
            $table->index(['revenue_opportunity_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('revenue_opportunity_id');
        });
        Schema::dropIfExists('revenue_opportunities');
    }
};
