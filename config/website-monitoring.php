<?php

return [
    'ssl_warning_days' => (int) env('WEBSITE_SSL_WARNING_DAYS', 30),
    'backup_warning_hours' => (int) env('WEBSITE_BACKUP_WARNING_HOURS', 36),
    'agent_stale_hours' => (int) env('WEBSITE_AGENT_STALE_HOURS', 24),
    'failure_threshold' => (int) env('WEBSITE_FAILURE_THRESHOLD', 2),
];
