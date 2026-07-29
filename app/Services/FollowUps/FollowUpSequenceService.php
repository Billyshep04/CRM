<?php

namespace App\Services\FollowUps;

use App\Models\FollowUpEnrolment;
use App\Models\FollowUpSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FollowUpSequenceService
{
    public function enrol(Model $subject, string $key, ?int $userId = null): FollowUpEnrolment
    {
        return DB::transaction(function () use ($subject, $key, $userId) {
            $seq = FollowUpSequence::with('steps')->where('key', $key)->where('active', true)->firstOrFail();
            $enrol = FollowUpEnrolment::firstOrCreate(['sequence_id' => $seq->id, 'subject_type' => $subject::class, 'subject_id' => $subject->id], ['public_id' => (string) Str::ulid(), 'status' => 'active', 'started_at' => now(), 'enrolled_by_user_id' => $userId]);
            if ($enrol->wasRecentlyCreated) {
                foreach ($seq->steps->where('active', true) as $step) {
                    $enrol->executions()->create(['step_id' => $step->id, 'due_at' => $enrol->started_at->copy()->addDays($step->delay_days), 'status' => 'pending']);
                }
            }

return $enrol->load('executions.step');
        });
    }

    public function cancel(Model $subject, string $reason = 'cancelled'): int
    {
        return DB::transaction(function () use ($subject, $reason) {
            $list = FollowUpEnrolment::whereMorphedTo('subject', $subject)->whereIn('status', ['active', 'paused'])->get();
            foreach ($list as $e) {
                $e->update(['status' => 'cancelled', 'ended_at' => now()]);
                $e->executions()->where('status', 'pending')->update(['status' => 'cancelled', 'failure_message' => $reason]);
            }

return $list->count();
        });
    }
}
