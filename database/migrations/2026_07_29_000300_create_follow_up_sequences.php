<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_sequences', function (Blueprint $t): void {
            $t->id();
            $t->string('key', 80)->unique();
            $t->string('name');
            $t->string('subject_type', 100);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('follow_up_sequence_steps', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('sequence_id')->constrained('follow_up_sequences')->cascadeOnDelete();
            $t->unsignedInteger('position');
            $t->unsignedInteger('delay_days');
            $t->string('channel', 20);
            $t->string('title');
            $t->text('template')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['sequence_id', 'position']);
        });
        Schema::create('follow_up_enrolments', function (Blueprint $t): void {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('sequence_id')->constrained('follow_up_sequences');
            $t->morphs('subject');
            $t->string('status', 20)->default('active');
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->foreignId('enrolled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['sequence_id', 'subject_type', 'subject_id'], 'followup_enrolment_unique');
            $t->index(['status', 'started_at']);
        });
        Schema::create('follow_up_executions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('enrolment_id')->constrained('follow_up_enrolments')->cascadeOnDelete();
            $t->foreignId('step_id')->constrained('follow_up_sequence_steps');
            $t->timestamp('due_at');
            $t->string('status', 20)->default('pending');
            $t->timestamp('executed_at')->nullable();
            $t->text('failure_message')->nullable();
            $t->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $t->timestamps();
            $t->unique(['enrolment_id', 'step_id']);
            $t->index(['status', 'due_at']);
        });
        $id = DB::table('follow_up_sequences')->insertGetId(['key' => 'proposal_default', 'name' => 'Default proposal follow-up', 'subject_type' => 'proposal', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[1, 2, 'email', 'Proposal follow-up'], [2, 5, 'task', 'Call about proposal'], [3, 10, 'email', 'Proposal follow-up'], [4, 20, 'task', 'Final proposal follow-up']] as [$p,$d,$c,$title]) {
            DB::table('follow_up_sequence_steps')->insert(['sequence_id' => $id, 'position' => $p, 'delay_days' => $d, 'channel' => $c, 'title' => $title, 'template' => 'Follow up on proposal {{proposal_number}}.', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $lead = DB::table('follow_up_sequences')->insertGetId(['key' => 'lead_default', 'name' => 'Default lead follow-up', 'subject_type' => 'business', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[1, 2, 'task', 'Follow up lead'], [2, 5, 'task', 'Call lead'], [3, 10, 'task', 'Follow up lead'], [4, 20, 'task', 'Final lead follow-up']] as [$p,$d,$c,$title]) {
            DB::table('follow_up_sequence_steps')->insert(['sequence_id' => $lead, 'position' => $p, 'delay_days' => $d, 'channel' => $c, 'title' => $title, 'template' => 'Follow up with {{business_name}}.', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_executions');
        Schema::dropIfExists('follow_up_enrolments');
        Schema::dropIfExists('follow_up_sequence_steps');
        Schema::dropIfExists('follow_up_sequences');
    }
};
