<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteLaunchStep extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['metadata' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function run() { return $this->belongsTo(WebsiteLaunchRun::class, 'website_launch_run_id'); }
}
