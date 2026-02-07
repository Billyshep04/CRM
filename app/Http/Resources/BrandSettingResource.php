<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\BrandSetting $resource
 */
class BrandSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'logo_file_id' => $this->logo_file_id,
            'updated_by_user_id' => $this->updated_by_user_id,
            'updated_at' => $this->updated_at,
            'logo_file' => new StoredFileResource($this->whenLoaded('logoFile')),
        ];
    }
}
