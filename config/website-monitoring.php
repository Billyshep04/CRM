<?php

return [
    'ssl_warning_days' => (int) env('WEBSITE_SSL_WARNING_DAYS', 30),
    'backup_warning_hours' => (int) env('WEBSITE_BACKUP_WARNING_HOURS', 36),
    'agent_stale_hours' => (int) env('WEBSITE_AGENT_STALE_HOURS', 24),
    'failure_threshold' => (int) env('WEBSITE_FAILURE_THRESHOLD', 2),
    'minimum_uptime_checks' => (int) env('WEBSITE_MINIMUM_UPTIME_CHECKS', 6),
    'freshness' => [
        'availability_minutes' => (int) env('WEBSITE_AVAILABILITY_STALE_MINUTES', 30),
        'ssl_hours' => (int) env('WEBSITE_SSL_STALE_HOURS', 36),
        'wordpress_hours' => (int) env('WEBSITE_WORDPRESS_STALE_HOURS', 8),
        'hosting_hours' => (int) env('WEBSITE_HOSTING_STALE_HOURS', 8),
        'performance_hours' => (int) env('WEBSITE_PERFORMANCE_STALE_HOURS', 48),
        'backup_hours' => (int) env('WEBSITE_BACKUP_STALE_HOURS', 48),
    ],
];
