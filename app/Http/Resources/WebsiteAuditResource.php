<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteAuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'website_id' => $this->website_id,
            'business_id' => $this->business_id,
            'version' => $this->version,
            'status' => $this->status->value,
            'requested_url' => $this->requested_url,
            'final_url' => $this->final_url,
            'http_status' => $this->http_status,
            'http_version' => $this->http_version,
            'scores' => [
                'overall' => $this->overall_score,
                'seo' => $this->seo_score,
                'performance' => $this->performance_score,
                'accessibility' => $this->accessibility_score,
                'security' => $this->security_score,
            ],
            'redirect_chain' => $this->redirect_chain,
            'results' => $this->when($this->status->value === 'completed', $this->structured_results),
            'failure' => $this->when($this->status->value === 'failed', [
                'code' => $this->failure_code,
                'message' => $this->failure_message,
            ]),
            'findings' => AuditFindingResource::collection($this->whenLoaded('findings')),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'failed_at' => $this->failed_at,
            'created_at' => $this->created_at,
        ];
    }
}
