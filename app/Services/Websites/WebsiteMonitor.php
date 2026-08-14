<?php

namespace App\Services\Websites;

use App\Models\Website;
use App\Models\WebsiteHealthCheck;
use App\Services\Hosting\HostingProviderManager;
use App\Services\WebsiteAnalysis\HttpWebsiteClient;
use Throwable;

class WebsiteMonitor
{
    public function __construct(private HttpWebsiteClient $client, private WebsiteIncidentManager $incidents, private HostingProviderManager $hosting) {}

    public function check(Website $website, string $type = 'http'): WebsiteHealthCheck
    {
        $started = hrtime(true); $httpStatus = null; $errors = []; $online = false;
        try {
            $result = $this->client->fetch($website->login_url);
            $httpStatus = $result['response']->status();
            $online = $httpStatus >= 200 && $httpStatus < 500;
            $responseTime = $result['duration_ms'];
        } catch (Throwable $e) {
            $responseTime = (int) round((hrtime(true) - $started) / 1_000_000);
            $errors[] = 'Website could not be reached.';
        }

        $failures = $online ? 0 : min(255, $website->consecutive_failures + 1);
        $critical = !$online && $failures >= config('website-monitoring.failure_threshold', 2);
        $status = $critical ? 'critical' : (!$online ? 'attention' : 'healthy');
        $metrics = [];
        if ($type !== 'http' && $website->wordpress_enabled && $website->agent_token_encrypted) {
            try { $metrics = array_intersect_key($this->client->fetchJson(rtrim($website->login_url, '/').'/wp-json/webstamp/v1/status', $website->agent_token_encrypted), array_flip(['wordpress_version', 'php_version', 'plugin_count', 'plugin_updates', 'theme_updates', 'database_size_bytes', 'site_health_status', 'last_successful_backup_at', 'backup_status', 'performance_score'])); }
            catch (Throwable) { $errors[] = 'WordPress agent status is unavailable.'; }
        }
        if ($type !== 'http' && $website->hosting_enabled && $website->hostingServer) {
            try { $metrics = [...$metrics, ...array_intersect_key($this->hosting->for($website->hostingServer)->accountMetrics($website->hostingServer, $website), array_flip(['disk_used_bytes', 'disk_limit_bytes', 'bandwidth_used_bytes']))]; }
            catch (Throwable) { $errors[] = 'Hosting usage is unavailable.'; }
        }
        $status = $critical ? 'critical' : ((!$online || count($errors) || (($metrics['plugin_updates'] ?? 0) + ($metrics['theme_updates'] ?? 0)) > 0) ? 'attention' : 'healthy');
        $check = WebsiteHealthCheck::create([...$metrics, 'website_id' => $website->id, 'checked_at' => now(), 'check_type' => $type, 'http_status' => $httpStatus, 'response_time_ms' => $responseTime, 'uptime_status' => $online ? 'online' : 'offline', 'overall_status' => $status, 'errors' => $errors]);
        $website->update(['last_checked_at' => now(), 'consecutive_failures' => $failures, 'status' => $status]);
        $this->incidents->sync($website, 'website_offline', $critical, 'critical', 'Website offline', 'Repeated monitoring checks could not reach the website.');
        $updates = (($metrics['plugin_updates'] ?? 0) + ($metrics['theme_updates'] ?? 0)) > 0;
        $this->incidents->sync($website, 'updates_available', $updates, 'warning', 'Updates available', 'WordPress plugins or themes need maintenance.');
        return $check;
    }
}
