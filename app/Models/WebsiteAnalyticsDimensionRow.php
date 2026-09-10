<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteAnalyticsDimensionRow extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'sessions' => 'integer',
        'total_users' => 'integer',
        'screen_page_views' => 'integer',
        'conversions' => 'integer',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(WebsiteAnalyticsSnapshot::class, 'website_analytics_snapshot_id');
    }
}
