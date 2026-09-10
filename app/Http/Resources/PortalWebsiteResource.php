<?php

namespace App\Http\Resources;

use App\Models\Website;
use App\Services\Analytics\WebsiteAnalyticsReportBuilder;
use App\Services\Websites\WebsiteStatusSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalWebsiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $visibility = array_merge(Website::defaultPortalVisibility(), $this->portal_visibility ?? []);
        $snapshot = app(WebsiteStatusSnapshot::class)->for($this->resource);
        $maintenance = array_intersect_key($snapshot['maintenance'], array_flip(['status', 'label', 'plugin_count', 'plugin_updates', 'theme_updates', 'core_updates', 'checked_at', 'stale']));

        return array_filter([
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'environment' => $this->environment,
            'development_domain' => $this->development_domain,
            'production_domain' => $this->production_domain,
            'public_url' => $this->publicUrl(),
            'status' => $visibility['status'] ? $snapshot['overall_status'] : null,
            'last_monitored_at' => $snapshot['last_monitored_at'],
            'project_status' => $this->provisioning_status ? match ($this->provisioning_status) { 'complete' => 'Website online', 'waiting_for_dns' => 'DNS connection pending', 'waiting_for_ssl' => 'SSL pending', 'failed' => 'Needs attention', default => 'Setup in progress' } : null,
            'availability' => $visibility['status'] ? $snapshot['availability']['label'] : null,
            'availability_detail' => $visibility['status'] ? $snapshot['availability'] : null,
            'uptime' => $visibility['uptime'] ? $snapshot['uptime'] : null,
            'uptime_percent' => $visibility['uptime'] ? $snapshot['uptime']['percent_30d'] : null,
            'ssl' => $visibility['ssl'] ? $snapshot['ssl'] : null,
            'backups' => $visibility['backup'] ? $snapshot['backups'] : null,
            'performance' => $visibility['performance'] ? $snapshot['performance'] : null,
            'maintenance' => $visibility['maintenance'] ? $maintenance : null,
            'hosting_usage' => $visibility['hosting_usage'] ? ['disk_used_bytes' => $this->hostingAccount?->disk_used_bytes, 'disk_limit_bytes' => $this->hostingAccount?->disk_limit_bytes] : null,
            'technical_details' => $visibility['technical_details'] ? ['wordpress_version' => $this->latestAgentCheck()?->wordpress_version, 'php_version' => $this->latestAgentCheck()?->php_version] : null,
            'analytics' => $visibility['analytics'] && $this->resource->analyticsConfigured() ? $this->analyticsPanel() : null,
            'activities' => $this->activities->map(fn ($activity) => ['title' => $activity->title, 'description' => $activity->description, 'performed_at' => $activity->performed_at]),
        ], static fn ($value) => $value !== null);
    }

    private function latestAgentCheck()
    {
        return $this->healthChecks()->whereNotNull('wordpress_checked_at')->latest('wordpress_checked_at')->first();
    }

    /**
     * A trimmed traffic dashboard for the customer portal. Stored data only,
     * and never the property id, raw payloads or sync error detail.
     *
     * @return array<string, mixed>
     */
    private function analyticsPanel(): array
    {
        $report = app(WebsiteAnalyticsReportBuilder::class)->build($this->resource, '28d');

        return [
            'range' => $report['range'] ?? null,
            'last_updated' => $this->google_analytics_last_synced_at,
            'totals' => $report['totals'] ?? [],
            'deltas' => $report['deltas'] ?? [],
            'secondary' => $report['secondary'] ?? null,
            'series' => $report['series'] ?? [],
            'top_pages' => array_slice($report['breakdowns']['page_path'] ?? [], 0, 5),
            'channels' => array_slice($report['breakdowns']['session_default_channel_group'] ?? [], 0, 5),
            'key_events' => $report['key_events'] ?? [],
        ];
    }

    private function publicUrl(): string
    {
        $scheme = strtolower((string) parse_url((string) $this->login_url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) $scheme = 'https';
        $host = parse_url(str_contains((string) $this->domain, '://') ? $this->domain : "{$scheme}://{$this->domain}", PHP_URL_HOST);
        return $host ? "{$scheme}://{$host}" : (string) $this->login_url;
    }
}
