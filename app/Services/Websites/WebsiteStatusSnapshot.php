<?php

namespace App\Services\Websites;

use App\Models\Website;
use App\Models\WebsiteHealthCheck;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class WebsiteStatusSnapshot
{
    public function for(Website $website): array
    {
        $checks = $website->healthChecks()->where('checked_at', '>=', now()->subDays(30))->get();
        $availability = $this->latest($checks, 'availability_checked_at') ?? $checks->filter(fn ($c) => $c->check_type !== 'agent' && in_array($c->uptime_status, ['online', 'degraded', 'offline'], true))->sortByDesc('checked_at')->first();
        $ssl = $this->latest($checks, 'ssl_checked_at') ?? $checks->filter(fn ($c) => ! in_array($c->ssl_status, [null, 'unknown'], true))->sortByDesc('checked_at')->first();
        $wordpress = $this->latest($checks, 'wordpress_checked_at') ?? $checks->filter(fn ($c) => $c->wordpress_version !== null || $c->plugin_count !== null)->sortByDesc('checked_at')->first();
        $performance = $this->latest($checks, 'performance_checked_at');
        $backup = $this->latest($checks, 'backup_checked_at') ?? $checks->filter(fn ($c) => $c->last_successful_backup_at !== null || ! in_array($c->backup_status, [null, 'unknown'], true))->sortByDesc('checked_at')->first();
        $availabilityAt = $availability?->availability_checked_at ?? $availability?->checked_at;
        $sslAt = $ssl?->ssl_checked_at ?? $ssl?->checked_at;
        $wordpressAt = $wordpress?->wordpress_checked_at ?? $wordpress?->checked_at;
        $performanceAt = $performance?->performance_checked_at ?? $performance?->checked_at;
        $backupAt = $backup?->backup_checked_at ?? $backup?->checked_at;
        $hostingAt = $website->hostingAccount?->last_metrics_synced_at;

        $availabilityStale = $this->stale($availabilityAt, now()->subMinutes(config('website-monitoring.freshness.availability_minutes', 30)));
        $sslStale = $this->stale($sslAt, now()->subHours(config('website-monitoring.freshness.ssl_hours', 36)));
        $wordpressStale = $this->stale($wordpressAt, now()->subHours(config('website-monitoring.freshness.wordpress_hours', 8)));
        $hostingStale = $this->stale($hostingAt, now()->subHours(config('website-monitoring.freshness.hosting_hours', 8)));
        $performanceStale = $this->stale($performanceAt, now()->subHours(config('website-monitoring.freshness.performance_hours', 48)));
        $backupStale = $this->stale($backupAt, now()->subHours(config('website-monitoring.freshness.backup_hours', 48)));

        $uptimeChecks = $checks->filter(fn (WebsiteHealthCheck $check) => ($check->availability_checked_at || $check->check_type !== 'agent') && in_array($check->uptime_status, ['online', 'degraded', 'offline'], true));
        $minimum = config('website-monitoring.minimum_uptime_checks', 6);
        $uptimeReliable = $uptimeChecks->count() >= $minimum;
        $uptime = $uptimeReliable ? round($uptimeChecks->where('uptime_status', 'online')->count() / $uptimeChecks->count() * 100, 2) : null;

        $availabilityStatus = $availabilityStale ? 'unknown' : ($availability?->uptime_status ?? 'unknown');
        $sslStatus = $sslStale ? 'unknown' : ($ssl?->ssl_status ?? 'unknown');
        $updates = $wordpressStale ? null : (($wordpress?->plugin_updates ?? 0) + ($wordpress?->theme_updates ?? 0) + (int) data_get($wordpress?->metrics, 'core_updates', 0));
        $backupStatus = $backupStale ? 'unknown' : ($backup?->backup_status ?? 'unknown');
        $performanceScore = $performanceStale ? null : $performance?->performance_score;
        $responseTime = $availabilityStale ? null : $availability?->response_time_ms;

        $overall = $this->overall($website, $availabilityStatus, $sslStatus, $updates, $backupStatus, $backup?->last_successful_backup_at, $performanceScore);

        return [
            'overall_status' => $overall,
            'last_monitored_at' => ($successful = $checks->where('uptime_status', 'online')->sortByDesc(fn ($check) => $check->availability_checked_at ?? $check->checked_at)->first()) ? ($successful->availability_checked_at ?? $successful->checked_at) : null,
            'availability' => [
                'status' => $availabilityStatus,
                'label' => match ($availabilityStatus) { 'online' => 'Online', 'degraded' => 'Degraded', 'offline' => 'Offline', default => 'Status temporarily unavailable' },
                'http_status' => $availabilityStale ? null : $availability?->http_status,
                'response_time_ms' => $responseTime,
                'checked_at' => $availabilityAt,
                'stale' => $availabilityStale,
            ],
            'uptime' => [
                'percent_30d' => $uptime,
                'valid_checks' => $uptimeChecks->count(),
                'reliable' => $uptimeReliable,
                'label' => $uptimeReliable ? "{$uptime}%" : 'Monitoring started recently',
            ],
            'ssl' => [
                'status' => $sslStatus,
                'label' => match ($sslStatus) { 'valid' => 'Secure', 'expiring' => 'Needs attention', 'invalid', 'expired' => 'Problem detected', default => 'Status temporarily unavailable' },
                'expires_at' => $sslStale ? null : $ssl?->ssl_expires_at,
                'days_remaining' => $sslStale ? null : $ssl?->ssl_days_remaining,
                'checked_at' => $sslAt,
                'stale' => $sslStale,
            ],
            'backups' => [
                'status' => $backupStatus,
                'label' => match ($backupStatus) { 'successful', 'protected', 'complete' => 'Protected', 'failed', 'overdue' => 'Needs attention', default => 'Information unavailable' },
                'last_successful_at' => $backupStale ? null : $backup?->last_successful_backup_at,
                'checked_at' => $backupAt,
                'stale' => $backupStale,
                'source' => data_get($backup?->metrics, 'backup_source'),
            ],
            'performance' => [
                'score' => $performanceScore,
                'label' => $performanceScore !== null ? match (true) { $performanceScore >= 90 => 'Excellent', $performanceScore >= 70 => 'Good', $performanceScore >= 50 => 'Needs improvement', default => 'Poor' } : ($responseTime !== null ? "{$responseTime}ms response" : 'Monitoring pending'),
                'response_time_ms' => $responseTime,
                'scoring_enabled' => $performanceScore !== null,
                'checked_at' => $performanceAt,
                'stale' => $performanceStale,
            ],
            'maintenance' => [
                'status' => $wordpressStale ? 'unknown' : ($updates > 0 ? ($website->management_enabled ? 'scheduled' : 'attention') : 'current'),
                'label' => $wordpressStale ? 'Status temporarily unavailable' : ($updates > 0 ? ($website->management_enabled ? 'Maintenance scheduled' : 'Attention required') : 'Up to date'),
                'plugin_count' => $wordpressStale ? null : $wordpress?->plugin_count,
                'plugin_updates' => $wordpressStale ? null : $wordpress?->plugin_updates,
                'theme_updates' => $wordpressStale ? null : $wordpress?->theme_updates,
                'core_updates' => $wordpressStale ? null : data_get($wordpress?->metrics, 'core_updates'),
                'wordpress_version' => $wordpressStale ? null : $wordpress?->wordpress_version,
                'php_version' => $wordpressStale ? null : $wordpress?->php_version,
                'site_health_status' => $wordpressStale ? null : $wordpress?->site_health_status,
                'checked_at' => $wordpressAt,
                'stale' => $wordpressStale,
            ],
            'diagnostics' => [
                'external_monitoring' => $this->diagnostic($availabilityAt, $availabilityStale, $availability ? 'connected' : 'pending'),
                'ssl' => $this->diagnostic($sslAt, $sslStale, $ssl ? $sslStatus : 'pending'),
                'wordpress_agent' => $this->diagnostic($wordpressAt, $wordpressStale, $wordpress ? 'connected' : ($website->wordpress_enabled ? 'not_connected' : 'not_required')),
                'hosting' => $this->diagnostic($hostingAt, $hostingStale, $website->hosting_enabled ? ($hostingAt ? 'connected' : 'pending') : 'external'),
                'performance' => $this->diagnostic($performanceAt, $performanceStale, $performanceScore !== null ? 'connected' : 'not_configured'),
                'backups' => $this->diagnostic($backupAt, $backupStale, $backup ? $backupStatus : 'unavailable'),
            ],
        ];
    }

    private function latest(Collection $checks, string $timestamp): ?WebsiteHealthCheck
    {
        return $checks->filter(fn (WebsiteHealthCheck $check) => $check->{$timestamp} !== null)->sortByDesc($timestamp)->first();
    }

    private function stale(?CarbonInterface $timestamp, CarbonInterface $cutoff): bool
    {
        return ! $timestamp || $timestamp->lt($cutoff);
    }

    private function overall(Website $website, string $availability, string $ssl, ?int $updates, string $backup, ?CarbonInterface $lastBackup, ?int $performance): string
    {
        if ($availability === 'offline' || in_array($ssl, ['invalid', 'expired'], true)) return 'critical';
        if ($availability === 'unknown') return 'unknown';
        if ($availability === 'degraded' || in_array($ssl, ['unknown', 'unavailable', 'expiring'], true) || ($updates ?? 0) > 0 || in_array($backup, ['failed', 'overdue'], true) || ($performance !== null && $performance < 50)) return 'attention';
        return 'healthy';
    }

    private function diagnostic(?CarbonInterface $checkedAt, bool $stale, string $status): array
    {
        return ['status' => $stale && $checkedAt ? 'stale' : $status, 'checked_at' => $checkedAt, 'stale' => $stale];
    }
}
