<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadDiscoveryRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id, 'provider' => $this->provider, 'query' => $this->query,
            'location' => $this->location, 'requested_limit' => $this->requested_limit, 'status' => $this->status,
            'results_found' => $this->results_found, 'leads_created' => $this->leads_created,
            'leads_updated' => $this->leads_updated, 'duplicates_skipped' => $this->duplicates_skipped,
            'auto_audit' => $this->auto_audit, 'failure_message' => $this->failure_message,
            'started_at' => $this->started_at, 'completed_at' => $this->completed_at, 'created_at' => $this->created_at,
        ];
    }
}
