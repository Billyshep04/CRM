<?php

namespace App\Services\Websites;

use App\Models\HostingMetricSnapshot;
use App\Models\Website;
use App\Models\WebsiteHealthCheck;
use App\Services\Hosting\HostingProviderManager;
use App\Services\WebsiteAnalysis\HttpWebsiteClient;
use Throwable;

class WebsiteMonitor
{
    public function __construct(
        private HttpWebsiteClient $client,
        private WebsiteIncidentManager $incidents,
        private HostingProviderManager $hosting,
        private WebsiteStatusSnapshot $snapshots,
        private SslCertificateInspector $ssl,
    ) {}

    public function check(Website $website, string $type = 'http'): WebsiteHealthCheck
    {
        $now = now(); $started = hrtime(true); $httpStatus = null; $errors = []; $availability = 'offline'; $metrics = []; $detailMetrics = [];
        try {
            $result = $this->client->fetch($this->publicUrl($website));
            $httpStatus = $result['response']->status();
            $availability = match (true) { $httpStatus >= 200 && $httpStatus < 400 => 'online', $httpStatus >= 400 && $httpStatus < 600 => 'degraded', default => 'offline' };
            $responseTime = $result['duration_ms'];
            $detailMetrics['final_url'] = $result['final_url']; $detailMetrics['redirects'] = $result['redirects'];
        } catch (Throwable) {
            $responseTime = (int) round((hrtime(true) - $started) / 1_000_000); $errors[] = 'Website could not be reached.';
        }
        $failures = $availability === 'offline' ? min(255, $website->consecutive_failures + 1) : 0;
        if ($availability === 'offline' && $failures < config('website-monitoring.failure_threshold', 2)) $availability = 'unknown';

        $latestSsl = $website->healthChecks()->whereNotNull('ssl_checked_at')->latest('ssl_checked_at')->first();
        $checkSsl = in_array($type, ['full', 'manual', 'ssl'], true) || ! $latestSsl || $latestSsl->ssl_checked_at->lt(now()->subHours(config('website-monitoring.freshness.ssl_hours', 36)));
        $ssl = $checkSsl ? $this->ssl->inspect($this->publicUrl($website)) : null;

        $agentReached = false;
        if (in_array($type, ['full', 'manual'], true) && $website->wordpress_enabled && $website->agent_token_encrypted) {
            try {
                $agent = $this->client->fetchJson($this->publicUrl($website).'/wp-json/webstamp/v1/status', $website->agent_token_encrypted);
                $allowed = ['wordpress_version', 'php_version', 'plugin_count', 'plugin_updates', 'theme_updates', 'database_size_bytes', 'site_health_status', 'last_successful_backup_at', 'backup_status'];
                $metrics = [...$metrics, ...array_intersect_key($agent, array_flip($allowed))];
                $detailMetrics = [...$detailMetrics, ...array_intersect_key($agent, array_flip(['active_theme', 'active_theme_version', 'active_plugin_count', 'inactive_plugin_count', 'core_updates', 'wp_cron_status', 'maintenance_mode', 'backup_source']))];
                if (array_key_exists('performance_score', $agent)) $detailMetrics['agent_reported_performance_score'] = $agent['performance_score'];
                $agentReached = true;
            } catch (Throwable) { $errors[] = 'WordPress agent status is unavailable.'; }
        }

        $hostingSynced = false;
        if ($type === 'full' && $website->hosting_enabled && $website->hostingServer) {
            try {
                $hostingMetrics = $this->hosting->for($website->hostingServer)->accountMetrics($website->hostingServer, $website);
                $metrics = [...$metrics, ...array_intersect_key($hostingMetrics, array_flip(['disk_used_bytes', 'disk_limit_bytes', 'bandwidth_used_bytes', 'bandwidth_limit_bytes']))];
                $this->storeHostingMetrics($website, $hostingMetrics); $hostingSynced = true;
            } catch (Throwable) { $errors[] = 'Hosting usage is unavailable.'; }
        }

        $check = WebsiteHealthCheck::create([
            ...$metrics, 'website_id' => $website->id, 'checked_at' => $now, 'availability_checked_at' => $now, 'check_type' => $type,
            'http_status' => $httpStatus, 'response_time_ms' => $responseTime, 'uptime_status' => $availability,
            'ssl_status' => $ssl['status'] ?? 'unknown', 'ssl_expires_at' => $ssl['expires_at'] ?? null, 'ssl_checked_at' => $ssl ? $now : null,
            'ssl_days_remaining' => $ssl['days_remaining'] ?? null, 'ssl_error_reason' => $ssl['error_reason'] ?? null,
            'wordpress_checked_at' => $agentReached ? $now : null,
            'backup_checked_at' => $agentReached && (array_key_exists('backup_status', $metrics) || array_key_exists('last_successful_backup_at', $metrics)) ? $now : null,
            'performance_checked_at' => null,
            'hosting_synced_at' => $hostingSynced ? $now : null, 'overall_status' => 'unknown', 'warnings' => [], 'errors' => $errors, 'metrics' => $detailMetrics,
        ]);
        $website->update(['last_checked_at' => $now, 'consecutive_failures' => $failures, ...($agentReached ? ['agent_last_seen_at' => $now, 'monitoring_enabled' => true] : [])]);
        $website->refresh(); $snapshot = $this->snapshots->for($website);
        $check->update(['overall_status' => $snapshot['overall_status']]); $website->update(['status' => $snapshot['overall_status']]);

        $this->incidents->sync($website, 'website_offline', $availability === 'offline', 'critical', 'Website offline', 'Repeated external monitoring checks could not reach the website.');
        $this->incidents->sync($website, 'ssl_problem', in_array($ssl['status'] ?? null, ['invalid', 'expired'], true), 'critical', 'SSL problem', 'The public HTTPS certificate needs attention.');
        $updates = (($metrics['plugin_updates'] ?? 0) + ($metrics['theme_updates'] ?? 0) + (int) ($detailMetrics['core_updates'] ?? 0)) > 0;
        if ($agentReached) $this->incidents->sync($website, 'updates_available', $updates, 'warning', 'Updates available', 'WordPress plugins, themes or core need maintenance.');
        return $check->fresh();
    }

    private function storeHostingMetrics(Website $website, array $hostingMetrics): void
    {
        if (! $website->hostingAccount) return;
        $fields = ['disk_used_bytes', 'disk_limit_bytes', 'bandwidth_used_bytes', 'bandwidth_limit_bytes', 'inode_used', 'inode_limit', 'database_count', 'database_usage_bytes', 'mailbox_count', 'mailbox_usage_bytes', 'php_version', 'ssl_status', 'ssl_issuer', 'ssl_expires_at', 'resource_limits', 'dns'];
        $website->hostingAccount->update([...array_intersect_key($hostingMetrics, array_flip($fields)), 'last_metrics_synced_at' => now()]);
        HostingMetricSnapshot::create(['hosting_account_id' => $website->hostingAccount->id, 'website_id' => $website->id, 'status' => $hostingMetrics['hosting_status'] ?? $website->hostingAccount->status, ...array_intersect_key($hostingMetrics, array_flip(['disk_used_bytes', 'disk_limit_bytes', 'bandwidth_used_bytes', 'bandwidth_limit_bytes', 'inode_used', 'inode_limit'])), 'metrics' => collect($hostingMetrics)->except(['disk_used_bytes', 'disk_limit_bytes', 'bandwidth_used_bytes', 'bandwidth_limit_bytes', 'inode_used', 'inode_limit'])->all(), 'captured_at' => now()]);
    }

    private function publicUrl(Website $website): string
    {
        $loginUrl = trim((string) $website->login_url); $scheme = strtolower((string) parse_url($loginUrl, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) $scheme = 'https';
        $domain = trim((string) $website->domain); $host = parse_url(str_contains($domain, '://') ? $domain : "{$scheme}://{$domain}", PHP_URL_HOST) ?: parse_url($loginUrl, PHP_URL_HOST); $port = parse_url($loginUrl, PHP_URL_PORT);
        return $scheme.'://'.$host.($port ? ':'.$port : '');
    }
}
