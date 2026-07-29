<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id, 'name' => $this->name, 'status' => $this->status, 'source' => $this->source,
            'website_url' => $this->website_url, 'phone' => $this->phone, 'address' => $this->address,
            'google_place_id' => $this->google_place_id, 'google_maps_url' => $this->google_maps_url,
            'primary_category' => $this->primary_category,
            'google_rating' => $this->google_rating, 'google_review_count' => $this->google_review_count,
            'domain_registered_at' => $this->domain_registered_at, 'design_quality_score' => $this->design_quality_score,
            'professionalism_score' => $this->professionalism_score, 'missing_features' => $this->missing_features,
            'lead_score' => $this->lead_score, 'lead_grade' => $this->lead_grade, 'lead_scored_at' => $this->lead_scored_at,
            'current_score' => new LeadScoreResource($this->whenLoaded('currentLeadScore')),
            'discovered_at' => $this->discovered_at, 'last_discovered_at' => $this->last_discovered_at,
            'contacted' => $this->contacted_at !== null, 'contacted_at' => $this->contacted_at,
            'contacted_by' => $this->whenLoaded('contactedBy', fn () => $this->contactedBy ? [
                'id' => $this->contactedBy->id,
                'name' => $this->contactedBy->name,
            ] : null),
            'customer_id' => $this->customer_id,
            'owner_user_id' => $this->owner_user_id, 'next_action_type' => $this->next_action_type, 'next_action_at' => $this->next_action_at,
            'next_action_notes' => $this->next_action_notes, 'last_activity_at' => $this->last_activity_at,
            'estimated_project_value' => $this->estimated_project_value, 'probability' => $this->probability,
            'weighted_value' => round((float) $this->estimated_project_value * (int) $this->probability / 100, 2),
            'expected_close_date' => $this->expected_close_date, 'service_sought' => $this->service_sought, 'proposal_id' => $this->proposal_id,
            'won_at' => $this->won_at, 'lost_at' => $this->lost_at, 'lost_reason' => $this->lost_reason, 'competitor_notes' => $this->competitor_notes,
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
