<?php

namespace App\Services\Sales;

use App\Enums\CallOutcome;
use App\Models\Business;
use App\Models\CrmActivity;
use App\Models\CrmTask;
use App\Models\RevenueOpportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivityRecorder
{
    public function record(Model $subject, array $data, int $userId): CrmActivity
    {
        return DB::transaction(function () use ($subject, $data, $userId): CrmActivity {
            $outcome = isset($data['outcome']) ? CallOutcome::tryFrom($data['outcome']) : null;
            if ($outcome?->requiresNextAction() && empty($data['next_action_at'])) {
                throw ValidationException::withMessages(['next_action_at' => ['This outcome requires a next action date and time.']]);
            }
            $activity = $subject->activities()->create([...$data, 'public_id' => (string) Str::ulid(), 'created_by_user_id' => $userId, 'occurred_at' => $data['occurred_at'] ?? now()]);
            $updates = ['last_activity_at' => $activity->occurred_at];
            if ($subject instanceof RevenueOpportunity && in_array($data['type'], ['call', 'email', 'meeting'], true)) {
                $updates['last_contacted_at'] = $activity->occurred_at;
            }
            if ($subject instanceof Business && $data['type'] === 'call') {
                $updates['contacted_at'] = $activity->occurred_at;
            }
            if (! empty($data['next_action_at'])) {
                $updates += ['next_action_at' => $data['next_action_at'], 'next_action_type' => $data['next_action_type'] ?? 'follow_up', 'next_action_notes' => $data['notes'] ?? null];
                CrmTask::query()->updateOrCreate(
                    ['source_type' => 'crm_activity', 'source_reference' => $activity->public_id],
                    ['business_id' => $subject instanceof Business ? $subject->id : null, 'revenue_opportunity_id' => $subject instanceof RevenueOpportunity ? $subject->id : null, 'assigned_to_user_id' => $subject->owner_user_id ?? $userId, 'created_by_user_id' => $userId, 'title' => 'Follow up: '.($subject->name ?? $subject->title), 'description' => $data['notes'] ?? null, 'priority' => 'normal', 'status' => 'pending', 'due_date' => date('Y-m-d', strtotime($data['next_action_at'])), 'due_at' => $data['next_action_at'], 'hours' => 0, 'minutes' => 0]
                );
            }
            $subject->update($updates);

            return $activity;
        });
    }
}
