<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Website extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'hosting_server_id',
        'subscription_id',
        'name',
        'domain',
        'login_url',
        'environment',
        'cpanel_username',
        'google_analytics_property_id',
        'google_analytics_dashboard_url',
        'wordpress_enabled',
        'management_enabled',
        'hosting_enabled',
        'status',
        'agent_token_hash',
        'agent_token_encrypted',
        'agent_last_seen_at',
        'last_checked_at',
        'consecutive_failures',
        'portal_visibility',
        'metadata',
        'notes',
        'login_token_encrypted',
    ];

    protected $casts = [
        'wordpress_enabled' => 'boolean', 'management_enabled' => 'boolean', 'hosting_enabled' => 'boolean',
        'agent_last_seen_at' => 'datetime', 'last_checked_at' => 'datetime', 'portal_visibility' => 'array', 'metadata' => 'array',
        'agent_token_encrypted' => 'encrypted',
    ];

    protected $hidden = [
        'login_token_encrypted',
        'agent_token_hash',
        'agent_token_encrypted',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function hostingServer(): BelongsTo { return $this->belongsTo(HostingServer::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function healthChecks(): HasMany { return $this->hasMany(WebsiteHealthCheck::class)->latest('checked_at'); }
    public function incidents(): HasMany { return $this->hasMany(WebsiteIncident::class)->latest('opened_at'); }
    public function activities(): HasMany { return $this->hasMany(WebsiteActivity::class)->latest('performed_at'); }
    public function latestHealthCheck(): HasOne { return $this->hasOne(WebsiteHealthCheck::class)->latestOfMany('checked_at'); }

    public static function defaultPortalVisibility(): array
    {
        return ['status' => true, 'uptime' => true, 'ssl' => true, 'backup' => true, 'performance' => true, 'maintenance' => true, 'hosting_usage' => false, 'technical_details' => false];
    }

    public function audits(): HasMany
    {
        return $this->hasMany(WebsiteAudit::class);
    }
}
