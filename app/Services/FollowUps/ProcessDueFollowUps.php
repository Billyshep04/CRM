<?php

namespace App\Services\FollowUps;

use App\Models\Business;
use App\Models\CrmTask;
use App\Models\FollowUpExecution;
use App\Models\Proposal;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessDueFollowUps
{
    public function run(): array
    {
        $done = 0;
        $failed = 0;
        FollowUpExecution::with(['step', 'enrolment.subject'])->where('status', 'pending')->where('due_at', '<=', now())->orderBy('id')->chunkById(100, function ($rows) use (&$done, &$failed) {
            foreach ($rows as $execution) {
                try {
                    DB::transaction(function () use ($execution) {
                        $locked = FollowUpExecution::lockForUpdate()->find($execution->id);
                        if (! $locked || $locked->status !== 'pending') {
                            return;
                        }$subject = $execution->enrolment->subject;
                        $task = CrmTask::updateOrCreate(['source_type' => 'follow_up_execution', 'source_reference' => (string) $execution->id], ['business_id' => $subject instanceof Business ? $subject->id : null, 'assigned_to_user_id' => $subject->owner_user_id ?? $subject->created_by_user_id ?? 1, 'created_by_user_id' => $execution->enrolment->enrolled_by_user_id, 'title' => $execution->step->title.': '.($subject instanceof Proposal ? $subject->proposal_number : ($subject->name ?? '')), 'description' => $execution->step->template, 'priority' => 'normal', 'status' => 'pending', 'due_date' => $execution->due_at->toDateString(), 'due_at' => $execution->due_at, 'hours' => 0, 'minutes' => 0]);
                        $locked->update(['status' => 'executed', 'executed_at' => now(), 'task_id' => $task->id]);
                    });
                    $done++;
                } catch (Throwable $e) {
                    $execution->update(['status' => 'failed', 'failure_message' => substr($e->getMessage(), 0, 2000)]);
                    report($e);
                    $failed++;
                }
            }
        });

        return compact('done', 'failed');
    }
}
