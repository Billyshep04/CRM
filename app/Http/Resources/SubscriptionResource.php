<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Subscription $resource
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'created_by_user_id' => $this->created_by_user_id,
            'description' => $this->description,
            'monthly_cost' => $this->monthly_cost,
            'billing_frequency' => $this->billing_frequency,
            'start_date' => $this->start_date,
            'next_invoice_date' => $this->next_invoice_date,
            'status' => $this->status,
            'paused_at' => $this->paused_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'months' => SubscriptionMonthResource::collection($this->whenLoaded('months')),
        ];
    }
}
