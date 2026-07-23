<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadIntelligenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $audits = $this->websiteAudits;
        $latestAudit = $audits->first(fn ($audit) => $audit->status->value === 'completed') ?? $audits->first();

        return [
            'lead' => new BusinessResource($this->resource),
            'latest_audit' => $latestAudit ? new WebsiteAuditResource($latestAudit) : null,
            'audit_history' => WebsiteAuditResource::collection($audits),
        ];
    }
}
