<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $t): void {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->morphs('subject');
            $t->string('type', 50);
            $t->string('direction', 20)->nullable();
            $t->string('outcome', 50)->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('occurred_at');
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('next_action_type', 50)->nullable();
            $t->timestamp('next_action_at')->nullable();
            $t->unsignedInteger('duration_minutes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['subject_type', 'subject_id', 'occurred_at'], 'activity_subject_timeline_idx');
        });
        Schema::create('pipeline_stage_transitions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('from_stage', 30)->nullable();
            $t->string('to_stage', 30);
            $t->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('occurred_at');
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['business_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stage_transitions');
        Schema::dropIfExists('crm_activities');
    }
};
