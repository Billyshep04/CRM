<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id, 'business_id' => $this->business?->public_id,
            'website_audit_id' => $this->websiteAudit?->public_id, 'profile_id' => $this->profile?->public_id,
            'score' => $this->score, 'grade' => $this->grade, 'confidence' => $this->confidence,
            'breakdown' => $this->breakdown, 'is_current' => $this->is_current, 'calculated_at' => $this->calculated_at,
        ];
    }
}
