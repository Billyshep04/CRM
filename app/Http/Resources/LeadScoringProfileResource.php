<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadScoringProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id, 'name' => $this->name, 'version' => $this->version,
            'description' => $this->description, 'is_default' => $this->is_default, 'is_active' => $this->is_active,
            'weights' => $this->whenLoaded('weights', fn () => $this->weights->mapWithKeys(fn ($weight) => [$weight->factor->value => $weight->weight])),
        ];
    }
}
