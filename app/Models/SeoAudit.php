<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAudit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array', 'has_sitemap' => 'boolean', 'has_robots_txt' => 'boolean'];
    }

    public function websiteAudit(): BelongsTo
    {
        return $this->belongsTo(WebsiteAudit::class);
    }
}
