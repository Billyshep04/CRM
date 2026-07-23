<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditFindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->check_key,
            'category' => $this->category,
            'severity' => $this->severity->value,
            'status' => $this->status,
            'title' => $this->title,
            'description' => $this->description,
            'evidence' => $this->evidence,
            'recommendation' => $this->recommendation,
        ];
    }
}
