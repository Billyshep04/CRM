<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Customer $resource
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'billing_address' => $this->billing_address,
            'notes' => $this->notes,
            'user_id' => $this->user_id,
            'created_by_user_id' => $this->created_by_user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'jobs_count' => $this->jobs_count,
            'subscriptions_count' => $this->subscriptions_count,
            'jobs_sum_cost' => $this->jobs_sum_cost,
            'subscriptions_sum_monthly_cost' => $this->subscriptions_sum_monthly_cost,
            'jobs' => JobResource::collection($this->whenLoaded('jobs')),
            'subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'websites' => WebsiteResource::collection($this->whenLoaded('websites')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
        ];
    }
}
