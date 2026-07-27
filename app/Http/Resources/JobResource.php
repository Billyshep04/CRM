<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Job $resource
 */
class JobResource extends JsonResource
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
            'notes' => $this->notes,
            'cost' => $this->cost,
            'status' => $this->status,
            'completed_at' => $this->completed_at,
            'invoiced_at' => $this->invoiced_at,
            'archived_at' => $this->archived_at,
            'is_archived' => $this->archived_at !== null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
