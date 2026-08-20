<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteHealthCheck extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'checked_at' => 'datetime', 'availability_checked_at' => 'datetime',
        'ssl_expires_at' => 'datetime', 'ssl_checked_at' => 'datetime',
        'wordpress_checked_at' => 'datetime', 'last_successful_backup_at' => 'datetime',
        'backup_checked_at' => 'datetime', 'performance_checked_at' => 'datetime',
        'hosting_synced_at' => 'datetime', 'warnings' => 'array', 'errors' => 'array',
        'metrics' => 'array',
    ];
    public function website(): BelongsTo { return $this->belongsTo(Website::class); }
}
