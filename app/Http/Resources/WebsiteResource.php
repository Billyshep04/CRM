<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'customer_id' => $this->customer_id, 'hosting_server_id' => $this->hosting_server_id, 'subscription_id' => $this->subscription_id,
            'name' => $this->name, 'domain' => $this->domain, 'login_url' => $this->login_url, 'environment' => $this->environment,
            'google_analytics_property_id' => $this->google_analytics_property_id, 'google_analytics_dashboard_url' => $this->google_analytics_dashboard_url,
            'cpanel_username' => $this->cpanel_username, 'wordpress_enabled' => $this->wordpress_enabled, 'management_enabled' => $this->management_enabled,
            'hosting_enabled' => $this->hosting_enabled, 'status' => $this->status,
            'agent_linked' => $this->agent_last_seen_at !== null,
            'agent_connected' => $this->agent_last_seen_at?->greaterThanOrEqualTo(now()->subHours(config('website-monitoring.agent_stale_hours', 24))) ?? false,
            'agent_last_seen_at' => $this->agent_last_seen_at, 'last_checked_at' => $this->last_checked_at, 'portal_visibility' => $this->portal_visibility,
            'notes' => $this->notes, 'metadata' => $this->metadata, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'hosting_server' => $this->whenLoaded('hostingServer', fn () => $this->hostingServer ? ['id' => $this->hostingServer->id, 'name' => $this->hostingServer->name, 'provider' => $this->hostingServer->provider, 'status' => $this->hostingServer->status] : null),
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
            'latest_health_check' => $this->whenLoaded('latestHealthCheck'),
            'health_checks' => $this->whenLoaded('healthChecks'), 'incidents' => $this->whenLoaded('incidents'), 'activities' => $this->whenLoaded('activities'),
        ];
    }
}
