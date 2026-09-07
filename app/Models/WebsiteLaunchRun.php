<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteLaunchRun extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['recovery_encrypted'];
    protected $attributes = ['state' => 'pending', 'attempts' => 0];
    protected $casts = [
        'options' => 'array',
        'recovery_encrypted' => 'encrypted:array',
        'dns_status' => 'array',
        'ssl_status' => 'array',
        'next_check_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function website() { return $this->belongsTo(Website::class); }
    public function account() { return $this->belongsTo(HostingAccount::class, 'hosting_account_id'); }
    public function initiatedBy() { return $this->belongsTo(User::class, 'initiated_by_user_id'); }
    public function steps() { return $this->hasMany(WebsiteLaunchStep::class)->orderBy('id'); }
}
