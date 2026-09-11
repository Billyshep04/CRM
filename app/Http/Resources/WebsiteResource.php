<?php

namespace App\Http\Resources;

use App\Services\Analytics\AnalyticsDriver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $agentConnected = ($this->agent_last_seen_at?->greaterThanOrEqualTo(now()->subHours(config('website-monitoring.agent_stale_hours', 24))) ?? false)
            && (! $this->agent_last_failed_at || $this->agent_last_seen_at->greaterThan($this->agent_last_failed_at));
        $snapshot = $this->resource->relationLoaded('healthChecks')
            ? app(\App\Services\Websites\WebsiteStatusSnapshot::class)->for($this->resource)
            : null;
        $lastEnqueued = cache('website-monitoring:last-enqueued-at');
        $monitoringSystem = [
            'status' => $lastEnqueued && \Carbon\Carbon::parse($lastEnqueued)->gte(now()->subMinutes(20)) ? 'healthy' : 'delayed',
            'checked_at' => $lastEnqueued,
            'stale' => ! $lastEnqueued || \Carbon\Carbon::parse($lastEnqueued)->lt(now()->subMinutes(20)),
        ];
        $launchIssues = [];
        if ($this->environment !== 'development') $launchIssues[] = 'This is already a live website.';
        if (! $this->resource->hasVerifiedHostingConnection()) $launchIssues[] = 'A current Krystal hosting connection is required.';
        if ($this->environment === 'development' && ! $this->hostingAccount?->automation_password_encrypted) $launchIssues[] = 'Automation access is not retained for this account.';
        $wordpressLogin = $this->resource->relationLoaded('wordpressCredential')
            ? $this->wordpressLoginState($this->wordpressCredential)
            : null;
        return [
            'id' => $this->id, 'customer_id' => $this->customer_id, 'hosting_server_id' => $this->hosting_server_id, 'hosting_account_id' => $this->hosting_account_id, 'subscription_id' => $this->subscription_id,
            'name' => $this->name, 'domain' => $this->domain, 'development_domain' => $this->development_domain, 'production_domain' => $this->production_domain, 'current_domain' => $this->current_domain ?: $this->domain, 'went_live_at' => $this->went_live_at, 'login_url' => $this->login_url, 'environment' => $this->environment,
            'google_analytics_property_id' => $this->google_analytics_property_id, 'google_analytics_dashboard_url' => $this->google_analytics_dashboard_url,
            'analytics' => [
                'enabled' => (bool) $this->google_analytics_enabled,
                'status' => $this->google_analytics_status ?? ($this->google_analytics_property_id ? 'unconfigured' : null),
                'last_synced_at' => $this->google_analytics_last_synced_at,
                'last_error' => $this->google_analytics_last_error,
                // Lets staff tell live Google Analytics apart from the mock
                // driver at a glance, instead of trusting a "connected" status
                // that the mock provider can also report.
                'driver' => AnalyticsDriver::currentOrUnknown(),
            ],
            'cpanel_username' => $this->cpanel_username, 'wordpress_enabled' => $this->wordpress_enabled, 'management_enabled' => $this->management_enabled, 'monitoring_enabled' => $this->monitoring_enabled,
            'hosting_enabled' => $this->hosting_enabled, 'status' => $this->status,
            'hosting_connected' => $this->resource->hasVerifiedHostingConnection(),
            'agent_linked' => $this->agent_last_seen_at !== null,
            'agent_connected' => $agentConnected,
            'agent_last_seen_at' => $this->agent_last_seen_at, 'agent_last_failed_at' => $this->agent_last_failed_at, 'last_checked_at' => $this->last_checked_at, 'portal_visibility' => $this->portal_visibility,
            'provisioning_status' => $this->provisioning_status, 'lifecycle_state' => $this->lifecycle_state, 'deletion_status' => $this->deletion_status,
            'go_live' => ['available' => $launchIssues === [], 'issues' => $launchIssues],
            'wordpress_login' => $this->when($wordpressLogin !== null, $wordpressLogin),
            'notes' => $this->notes, 'metadata' => $this->metadata, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'hosting_server' => $this->whenLoaded('hostingServer', fn () => $this->hostingServer ? ['id' => $this->hostingServer->id, 'name' => $this->hostingServer->name, 'provider' => $this->hostingServer->provider, 'status' => $this->hostingServer->status] : null),
            'hosting_account' => $this->whenLoaded('hostingAccount', fn () => $this->hostingAccount ? ['id'=>$this->hostingAccount->id,'username'=>$this->hostingAccount->username,'primary_domain'=>$this->hostingAccount->primary_domain,'assigned_ip'=>$this->hostingAccount->assigned_ip,'package_name'=>$this->hostingAccount->package_name,'status'=>$this->hostingAccount->status,'provider_missing'=>$this->hostingAccount->provider_missing,'provider_missing_at'=>$this->hostingAccount->provider_missing_at,'last_synced_at'=>$this->hostingAccount->last_synced_at,'disk_used_bytes'=>$this->hostingAccount->disk_used_bytes,'disk_limit_bytes'=>$this->hostingAccount->disk_limit_bytes,'bandwidth_used_bytes'=>$this->hostingAccount->bandwidth_used_bytes,'bandwidth_limit_bytes'=>$this->hostingAccount->bandwidth_limit_bytes,'inode_used'=>$this->hostingAccount->inode_used,'inode_limit'=>$this->hostingAccount->inode_limit,'database_count'=>$this->hostingAccount->database_count,'database_usage_bytes'=>$this->hostingAccount->database_usage_bytes,'mailbox_count'=>$this->hostingAccount->mailbox_count,'mailbox_usage_bytes'=>$this->hostingAccount->mailbox_usage_bytes,'php_version'=>$this->hostingAccount->php_version,'ssl_status'=>$this->hostingAccount->ssl_status,'ssl_issuer'=>$this->hostingAccount->ssl_issuer,'ssl_expires_at'=>$this->hostingAccount->ssl_expires_at,'resource_limits'=>$this->hostingAccount->resource_limits,'dns'=>$this->hostingAccount->dns,'last_metrics_synced_at'=>$this->hostingAccount->last_metrics_synced_at] : null),
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
            'latest_health_check' => $this->whenLoaded('latestHealthCheck'),
            'health_checks' => $this->whenLoaded('healthChecks'), 'incidents' => $this->whenLoaded('incidents'), 'activities' => $this->whenLoaded('activities'),
            'provisioning_runs' => $this->whenLoaded('provisioningRuns'),
            'launch_runs' => $this->whenLoaded('launchRuns'),
            'customer_snapshot' => $this->when($snapshot !== null, $snapshot),
            'data_source_diagnostics' => $this->when($snapshot !== null, [...($snapshot['diagnostics'] ?? []), 'monitoring_system' => $monitoringSystem]),
        ];
    }

    private function wordpressLoginState($credential): array
    {
        $available = $credential && ! $credential->revealed_at && ! $credential->revoked_at;

        return [
            'reveal_available' => (bool) $available,
            'revealed' => (bool) ($credential?->revealed_at),
            'reset_available' => (bool) ($this->wordpress_enabled && $this->hostingAccount?->getRawOriginal('automation_password_encrypted')),
        ];
    }
}
