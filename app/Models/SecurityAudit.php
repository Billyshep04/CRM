<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAudit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'details' => 'array', 'uses_https' => 'boolean', 'ssl_valid' => 'boolean',
            'has_hsts' => 'boolean', 'has_csp' => 'boolean', 'has_frame_protection' => 'boolean',
        ];
    }

    public function websiteAudit(): BelongsTo
    {
        return $this->belongsTo(WebsiteAudit::class);
    }
}
