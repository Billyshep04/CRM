<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('next_action_type', 50)->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->text('next_action_notes')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->decimal('estimated_project_value', 12, 2)->nullable();
            $table->unsignedTinyInteger('probability')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->string('service_sought', 100)->nullable();
            $table->foreignId('proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason', 50)->nullable();
            $table->text('competitor_notes')->nullable();
            $table->index(['owner_user_id', 'status', 'next_action_at'], 'business_owner_stage_action_idx');
            $table->index(['status', 'expected_close_date']);
        });
        DB::table('businesses')->where('status', 'reviewing')->update(['status' => 'new']);
        DB::table('businesses')->where('status', 'converted')->update(['status' => 'won', 'won_at' => now()]);
        DB::table('businesses')->where('status', 'disqualified')->update(['status' => 'lost', 'lost_reason' => 'invalid_unqualified', 'lost_at' => now()]);
        Schema::table('revenue_opportunities', function (Blueprint $table): void {
            $table->string('next_action_type', 50)->nullable();
            $table->text('next_action_notes')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('lost_reason', 50)->nullable();
            $table->text('competitor_notes')->nullable();
        });
        Schema::table('proposals', function (Blueprint $table): void {
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('revenue_opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lost_reason', 50)->nullable();
        });
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('source_type', 100)->nullable();
            $table->string('source_reference', 191)->nullable();
            $table->timestamp('due_at')->nullable();
            $table->unique(['source_type', 'source_reference'], 'tasks_source_unique');
            $table->index(['assigned_to_user_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $t) => $t->dropForeign(['business_id']));
        Schema::table('tasks', fn (Blueprint $t) => $t->dropColumn(['business_id', 'source_type', 'source_reference', 'due_at']));
        Schema::table('proposals', fn (Blueprint $t) => $t->dropForeign(['business_id']));
        Schema::table('proposals', fn (Blueprint $t) => $t->dropForeign(['revenue_opportunity_id']));
        Schema::table('proposals', fn (Blueprint $t) => $t->dropColumn(['business_id', 'revenue_opportunity_id', 'lost_reason']));
        Schema::table('revenue_opportunities', fn (Blueprint $t) => $t->dropColumn(['next_action_type', 'next_action_notes', 'last_contacted_at', 'last_activity_at', 'lost_reason', 'competitor_notes']));
        Schema::table('businesses', fn (Blueprint $t) => $t->dropForeign(['proposal_id']));
        Schema::table('businesses', fn (Blueprint $t) => $t->dropColumn(['next_action_type', 'next_action_at', 'next_action_notes', 'last_activity_at', 'estimated_project_value', 'probability', 'expected_close_date', 'service_sought', 'proposal_id', 'won_at', 'lost_at', 'lost_reason', 'competitor_notes']));
    }
};
