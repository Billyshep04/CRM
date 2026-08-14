<?php

namespace App\Http\Resources;

use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalWebsiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $visibility = array_merge(Website::defaultPortalVisibility(), $this->portal_visibility ?? []);
        $check = $this->latestHealthCheck;
        $checks = $this->healthChecks->where('checked_at', '>=', now()->subDays(30));
        $uptime = $checks->count() ? round($checks->where('uptime_status', 'online')->count() / $checks->count() * 100, 2) : null;
        return array_filter([
            'id' => $this->id, 'name' => $this->name, 'domain' => $this->domain, 'public_url' => $this->login_url, 'login_url' => $this->login_url,
            'status' => $visibility['status'] ? $this->status : null, 'last_monitored_at' => $this->last_checked_at,
            'availability' => $visibility['status'] ? ($check?->uptime_status === 'online' ? 'Online' : ($check?->uptime_status === 'offline' ? 'Offline' : 'Not checked')) : null,
            'uptime_percent' => $visibility['uptime'] ? $uptime : null,
            'ssl' => $visibility['ssl'] ? ['status' => $check?->ssl_status, 'expires_at' => $check?->ssl_expires_at] : null,
            'backups' => $visibility['backup'] ? ['status' => $check?->backup_status, 'last_successful_at' => $check?->last_successful_backup_at] : null,
            'performance' => $visibility['performance'] ? ['score' => $check?->performance_score, 'response_time_ms' => $check?->response_time_ms] : null,
            'maintenance' => $visibility['maintenance'] ? ['plugin_count' => $check?->plugin_count, 'plugin_updates' => $check?->plugin_updates, 'theme_updates' => $check?->theme_updates, 'status' => (($check?->plugin_updates ?? 0) + ($check?->theme_updates ?? 0)) ? 'Maintenance required' : 'Up to date'] : null,
            'hosting_usage' => $visibility['hosting_usage'] ? ['disk_used_bytes' => $check?->disk_used_bytes, 'disk_limit_bytes' => $check?->disk_limit_bytes] : null,
            'technical_details' => $visibility['technical_details'] ? ['wordpress_version' => $check?->wordpress_version, 'php_version' => $check?->php_version] : null,
            'activities' => $this->activities->map(fn ($activity) => ['title' => $activity->title, 'description' => $activity->description, 'performed_at' => $activity->performed_at]),
        ], static fn ($value) => $value !== null);
    }
}
