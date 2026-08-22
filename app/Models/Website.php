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
        'hosting_account_id',
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
        'monitoring_enabled',
        'hosting_enabled',
        'status',
        'provisioning_status',
        'lifecycle_state',
        'deletion_status',
        'agent_token_hash',
        'agent_token_encrypted',
        'agent_last_seen_at',
        'agent_last_failed_at',
        'last_checked_at',
        'consecutive_failures',
        'portal_visibility',
        'metadata',
        'notes',
        'login_token_encrypted',
    ];

    protected $casts = [
        'wordpress_enabled' => 'boolean', 'management_enabled' => 'boolean', 'monitoring_enabled' => 'boolean', 'hosting_enabled' => 'boolean',
        'agent_last_seen_at' => 'datetime', 'agent_last_failed_at' => 'datetime', 'last_checked_at' => 'datetime', 'portal_visibility' => 'array', 'metadata' => 'array',
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
    public function hostingAccount(): BelongsTo { return $this->belongsTo(HostingAccount::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function healthChecks(): HasMany { return $this->hasMany(WebsiteHealthCheck::class)->latest('checked_at'); }
    public function incidents(): HasMany { return $this->hasMany(WebsiteIncident::class)->latest('opened_at'); }
    public function activities(): HasMany { return $this->hasMany(WebsiteActivity::class)->latest('performed_at'); }
    public function latestHealthCheck(): HasOne { return $this->hasOne(WebsiteHealthCheck::class)->latestOfMany('checked_at'); }
    public function provisioningRuns(): HasMany { return $this->hasMany(WebsiteProvisioningRun::class)->latest(); }
    public function credentials(): HasMany { return $this->hasMany(WebsiteCredential::class); }

    public function hasVerifiedHostingConnection(): bool
    {
        $account = $this->relationLoaded('hostingAccount') ? $this->hostingAccount : $this->hostingAccount()->first();
        $server = $this->relationLoaded('hostingServer') ? $this->hostingServer : $this->hostingServer()->first();
        if (! $this->hosting_enabled || ! $account || ! $server || $server->api_type !== 'whm' || ! $account->last_synced_at) return false;
        if (data_get($account->metadata, 'mock', false)) return false;

        $domain = $this->normaliseDomain($this->domain ?: $this->login_url);
        $accountDomains = collect($account->domains ?? [])
            ->map(fn ($item) => is_array($item) ? ($item['domain'] ?? null) : $item)
            ->push($account->primary_domain)
            ->filter()
            ->map(fn ($item) => $this->normaliseDomain((string) $item));

        return $domain !== '' && $accountDomains->contains($domain);
    }

    private function normaliseDomain(string $value): string
    {
        $value = strtolower(trim($value));
        $host = parse_url(str_contains($value, '://') ? $value : "https://{$value}", PHP_URL_HOST) ?: $value;

        return preg_replace('/^www\./', '', rtrim($host, '.'));
    }

    public static function defaultPortalVisibility(): array
    {
        return ['status' => true, 'uptime' => true, 'ssl' => true, 'backup' => true, 'performance' => true, 'maintenance' => true, 'hosting_usage' => false, 'technical_details' => false];
    }

    public function audits(): HasMany
    {
        return $this->hasMany(WebsiteAudit::class);
    }
}
