<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteIncident extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['opened_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime', 'metadata' => 'array'];
    public function website(): BelongsTo { return $this->belongsTo(Website::class); }
}
