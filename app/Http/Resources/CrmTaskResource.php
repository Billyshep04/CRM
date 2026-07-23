<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'created_by_user_id' => $this->created_by_user_id,
            'job_id' => $this->job_id,
            'revenue_opportunity_id' => $this->revenue_opportunity_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_date' => $this->due_date,
            'hours' => (int) $this->hours,
            'minutes' => (int) $this->minutes,
            'total_minutes' => $this->totalMinutes(),
            'staff_notes' => $this->staff_notes,
            'completed_at' => $this->completed_at,
            'reminder_sent_at' => $this->reminder_sent_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => [
                'id' => $this->assignedTo?->id,
                'name' => $this->assignedTo?->name,
                'email' => $this->assignedTo?->email,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
                'email' => $this->createdBy?->email,
            ]),
            'job' => new JobResource($this->whenLoaded('job')),
            'revenue_opportunity' => new RevenueOpportunityResource($this->whenLoaded('revenueOpportunity')),
        ];
    }
}
