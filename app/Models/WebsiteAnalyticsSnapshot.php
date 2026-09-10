<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteAnalyticsSnapshot extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        // period_start / period_end are kept as plain Y-m-d strings so range
        // comparisons stay simple and portable across sqlite/mysql.
        'fetched_at' => 'datetime',
        'sessions' => 'integer',
        'total_users' => 'integer',
        'new_users' => 'integer',
        'engaged_sessions' => 'integer',
        'screen_page_views' => 'integer',
        'avg_engagement_time_seconds' => 'integer',
        'engagement_rate' => 'float',
        'bounce_rate' => 'float',
        'event_count' => 'integer',
        'conversions' => 'integer',
        'key_events' => 'array',
        'source_data' => 'array',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function dimensionRows(): HasMany
    {
        return $this->hasMany(WebsiteAnalyticsDimensionRow::class);
    }
}
