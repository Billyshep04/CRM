<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteActivity extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['performed_at' => 'datetime', 'visible_to_customer' => 'boolean', 'metadata' => 'array'];
    public function website(): BelongsTo { return $this->belongsTo(Website::class); }
}
