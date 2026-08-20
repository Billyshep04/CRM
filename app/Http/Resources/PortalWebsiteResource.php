<?php

namespace App\Http\Resources;

use App\Models\Website;
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
            'public_url' => $this->publicUrl(),
            'status' => $visibility['status'] ? $snapshot['overall_status'] : null,
            'last_monitored_at' => $snapshot['last_monitored_at'],
            'project_status' => $this->provisioning_status ? match ($this->provisioning_status) { 'complete' => 'Ready', 'failed' => 'Needs attention', default => 'In development' } : null,
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
            'activities' => $this->activities->map(fn ($activity) => ['title' => $activity->title, 'description' => $activity->description, 'performed_at' => $activity->performed_at]),
        ], static fn ($value) => $value !== null);
    }

    private function latestAgentCheck()
    {
        return $this->healthChecks()->whereNotNull('wordpress_checked_at')->latest('wordpress_checked_at')->first();
    }

    private function publicUrl(): string
    {
        $scheme = strtolower((string) parse_url((string) $this->login_url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) $scheme = 'https';
        $host = parse_url(str_contains((string) $this->domain, '://') ? $this->domain : "{$scheme}://{$this->domain}", PHP_URL_HOST);
        return $host ? "{$scheme}://{$host}" : (string) $this->login_url;
    }
}
