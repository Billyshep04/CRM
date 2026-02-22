<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Cost $resource
 */
class CostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'amount' => $this->amount,
            'incurred_on' => $this->incurred_on,
            'is_recurring' => (bool) $this->is_recurring,
            'recurring_frequency' => $this->recurring_frequency,
            'notes' => $this->notes,
            'receipt_file_id' => $this->receipt_file_id,
            'created_by_user_id' => $this->created_by_user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'receipt_file' => new StoredFileResource($this->whenLoaded('receiptFile')),
        ];
    }
}
