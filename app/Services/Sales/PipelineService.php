<?php

namespace App\Services\Sales;

use App\Enums\LeadPipelineStage;
use App\Models\Business;
use App\Models\CrmActivity;
use App\Models\PipelineStageTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PipelineService
{
    public function transition(Business $business, array $data, int $userId): Business
    {
        $to = LeadPipelineStage::from($data['stage']);
        $from = LeadPipelineStage::tryFrom($business->status);
        if ($to === LeadPipelineStage::Lost && empty($data['lost_reason'])) {
            throw ValidationException::withMessages(['lost_reason' => ['A lost reason is required.']]);
        } app(NextActionValidator::class)->business(['status' => $to->value, 'next_action_at' => $data['next_action_at'] ?? $business->next_action_at], $business->status);

        return DB::transaction(function () use ($business, $data, $userId, $to, $from) {
            $business->update(['status' => $to->value, 'next_action_at' => $to->isActive() ? ($data['next_action_at'] ?? $business->next_action_at) : null, 'next_action_type' => $data['next_action_type'] ?? $business->next_action_type, 'next_action_notes' => $data['next_action_notes'] ?? $business->next_action_notes, 'lost_reason' => $data['lost_reason'] ?? null, 'competitor_notes' => $data['competitor_notes'] ?? null, 'won_at' => $to === LeadPipelineStage::Won ? now() : $business->won_at, 'lost_at' => $to === LeadPipelineStage::Lost ? now() : $business->lost_at, 'last_activity_at' => now()]);
            PipelineStageTransition::create(['business_id' => $business->id, 'from_stage' => $from?->value, 'to_stage' => $to->value, 'changed_by_user_id' => $userId, 'occurred_at' => now()]);
            CrmActivity::create(['public_id' => (string) Str::ulid(), 'subject_type' => Business::class, 'subject_id' => $business->id, 'type' => 'status_change', 'notes' => 'Pipeline moved from '.($from?->value ?? 'unknown').' to '.$to->value.'.', 'occurred_at' => now(), 'created_by_user_id' => $userId, 'metadata' => ['from' => $from?->value, 'to' => $to->value]]);

            return $business->fresh();
        });
    }
}
