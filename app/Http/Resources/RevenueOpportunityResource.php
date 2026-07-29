<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RevenueOpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id, 'customer_id' => $this->customer_id, 'website_id' => $this->website_id,
            'owner_user_id' => $this->owner_user_id, 'type' => $this->type->value, 'type_label' => $this->type->label(),
            'status' => $this->status->value, 'title' => $this->title, 'recommendation' => $this->recommendation,
            'notes' => $this->notes, 'source' => $this->source, 'confidence' => $this->confidence,
            'estimated_project_value' => $this->estimated_project_value,
            'estimated_monthly_revenue' => $this->estimated_monthly_revenue,
            'annual_recurring_revenue' => round((float) $this->estimated_monthly_revenue * 12, 2),
            'renewal_due_at' => $this->renewal_due_at, 'next_action_at' => $this->next_action_at,
            'next_action_type' => $this->next_action_type, 'next_action_notes' => $this->next_action_notes,
            'last_contacted_at' => $this->last_contacted_at, 'last_activity_at' => $this->last_activity_at,
            'lost_reason' => $this->lost_reason, 'competitor_notes' => $this->competitor_notes,
            'won_at' => $this->won_at, 'lost_at' => $this->lost_at,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'website' => new WebsiteResource($this->whenLoaded('website')),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
